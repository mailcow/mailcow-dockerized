// Package bus implements the Redis Pub/Sub control bus: subscribing to the
// service's control channel, dispatching envelopes to a commands.Table, and
// publishing responses back to env.ReplyTo.
package bus

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"sync"
	"time"

	"github.com/redis/go-redis/v9"

	"github.com/mailcow/mailcow-dockerized/agent/internal/commands"
	"github.com/mailcow/mailcow-dockerized/agent/internal/envelope"
)

// ControlChannel assembles the per-service control channel.
func ControlChannel(service string) string { return "mailcow.control." + service }

// Server subscribes to a control channel and dispatches commands.
type Server struct {
	rdb     *redis.Client
	table   *commands.Table
	nodeID  string
	dedupe  *lru
	stop    chan struct{}
	stopped sync.Once
	wg      sync.WaitGroup
}

// NewServer wires a fresh server. nodeID is stamped into every Response and is
// what the backend's fan-in aggregator uses to attribute results.
func NewServer(rdb *redis.Client, table *commands.Table, nodeID string) *Server {
	return &Server{
		rdb:    rdb,
		table:  table,
		nodeID: nodeID,
		dedupe: newLRU(1024),
		stop:   make(chan struct{}),
	}
}

// Run blocks, subscribing to ControlChannel(service) and dispatching incoming
// envelopes concurrently. It returns when ctx is done or Shutdown is called.
func (s *Server) Run(ctx context.Context) error {
	channel := ControlChannel(s.table.Service)
	sub := s.rdb.Subscribe(ctx, channel)
	defer sub.Close()
	if _, err := sub.Receive(ctx); err != nil {
		return fmt.Errorf("bus: subscribe %s: %w", channel, err)
	}
	msgs := sub.Channel()
	for {
		select {
		case <-ctx.Done():
			s.wg.Wait()
			return ctx.Err()
		case <-s.stop:
			s.wg.Wait()
			return nil
		case m, ok := <-msgs:
			if !ok {
				s.wg.Wait()
				return errors.New("bus: subscription channel closed")
			}
			s.wg.Add(1)
			go func(payload string) {
				defer s.wg.Done()
				s.dispatch(ctx, payload)
			}(m.Payload)
		}
	}
}

// Shutdown signals Run to stop and waits for in-flight handlers (bounded by
// ctx).
func (s *Server) Shutdown(ctx context.Context) error {
	s.stopped.Do(func() { close(s.stop) })
	done := make(chan struct{})
	go func() { s.wg.Wait(); close(done) }()
	select {
	case <-done:
		return nil
	case <-ctx.Done():
		return ctx.Err()
	}
}

func (s *Server) dispatch(parent context.Context, payload string) {
	var req envelope.Request
	if err := json.Unmarshal([]byte(payload), &req); err != nil {
		// Malformed envelope: no RequestID/ReplyTo we can trust — drop.
		return
	}
	if req.RequestID != "" && !s.dedupe.add(req.RequestID) {
		// Duplicate (retry of an idempotent command): silently absorb.
		return
	}
	// Per-node targeting: if args.target_node is set and doesn't match us,
	// drop silently. The intended replica picks it up and replies.
	if target, ok := req.Args["target_node"].(string); ok && target != "" && target != s.nodeID {
		return
	}

	ctx, cancel := handlerContext(parent, req.Deadline)
	defer cancel()

	start := time.Now()
	resp := envelope.Response{RequestID: req.RequestID, OK: true, Node: s.nodeID}

	if h := s.table.Lookup(req.Cmd); h == nil {
		resp.OK = false
		resp.Error = fmt.Sprintf("no handler for cmd %q", req.Cmd)
		resp.ErrorCode = envelope.ErrCodeUnsupportedCommand
	} else {
		result, err := runWithRecover(ctx, h, req.Args)
		switch {
		case err == nil:
			resp.Result = result
		case errors.Is(err, commands.ErrNotFound):
			resp.OK = false
			resp.Error = err.Error()
			resp.ErrorCode = envelope.ErrCodeNotFound
		case errors.Is(err, commands.ErrValidation):
			resp.OK = false
			resp.Error = err.Error()
			resp.ErrorCode = envelope.ErrCodeValidation
		case errors.Is(err, context.DeadlineExceeded), errors.Is(ctx.Err(), context.DeadlineExceeded):
			resp.OK = false
			resp.Error = err.Error()
			resp.ErrorCode = envelope.ErrCodeTimeout
		default:
			resp.OK = false
			resp.Error = err.Error()
			resp.ErrorCode = envelope.ErrCodeInternal
		}
	}
	resp.DurationMS = time.Since(start).Milliseconds()

	if req.ReplyTo == "" {
		return
	}
	data, err := json.Marshal(resp)
	if err != nil {
		return
	}
	// Replies go through a List (RPUSH + EXPIRE), not Pub/Sub. This sidesteps
	// the "subscribe-before-publish" race and lets the backend fan-in via
	// BLPOP — important because PhpRedis's subscribe() blocks, so we can't
	// publish on the same connection after subscribing. Use parent ctx so a
	// per-handler deadline can't stop us from delivering the timeout response.
	pipe := s.rdb.Pipeline()
	pipe.RPush(parent, req.ReplyTo, data)
	pipe.Expire(parent, req.ReplyTo, 60*time.Second)
	_, _ = pipe.Exec(parent)
}

func runWithRecover(ctx context.Context, h commands.Handler, args map[string]any) (out any, err error) {
	defer func() {
		if r := recover(); r != nil {
			err = fmt.Errorf("handler panic: %v", r)
		}
	}()
	return h(ctx, args)
}

func handlerContext(parent context.Context, deadline time.Time) (context.Context, context.CancelFunc) {
	if deadline.IsZero() {
		return context.WithCancel(parent)
	}
	return context.WithDeadline(parent, deadline)
}
