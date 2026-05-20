// Per-container control-bus subscriber. Subscribes to mailcow.control.<service>
// on Redis, runs handlers from the per-service command table, publishes
// heartbeats and stats. Optionally supervises a child process.
package main

import (
	"context"
	"errors"
	"fmt"
	"log"
	"os"
	"os/signal"
	"strings"
	"sync"
	"sync/atomic"
	"syscall"
	"time"

	"github.com/redis/go-redis/v9"

	"github.com/mailcow/mailcow-dockerized/agent/internal/bus"
	"github.com/mailcow/mailcow-dockerized/agent/internal/commands"
	"github.com/mailcow/mailcow-dockerized/agent/internal/proc"
	"github.com/mailcow/mailcow-dockerized/agent/internal/registry"
	"github.com/mailcow/mailcow-dockerized/agent/internal/services"
	"github.com/mailcow/mailcow-dockerized/agent/internal/stats"
)

const agentVersion = "0.1.0"

// atomicSignal shares the last caught terminal signal between the handler
// goroutine and main() so it can be forwarded to the supervised child.
type atomicSignal struct{ v atomic.Int32 }

func (a *atomicSignal) Store(s syscall.Signal) { a.v.Store(int32(s)) }
func (a *atomicSignal) Load() os.Signal        { return syscall.Signal(a.v.Load()) }

// healthState holds the latest health probe result. Written by the probe loop,
// read by the heartbeat loop.
type healthState struct {
	mu     sync.RWMutex
	ok     bool
	detail string
	at     time.Time
}

func (h *healthState) Set(ok bool, detail string) {
	h.mu.Lock()
	h.ok = ok
	h.detail = detail
	h.at = time.Now()
	h.mu.Unlock()
}

func (h *healthState) Snapshot() (ok bool, detail string, at time.Time) {
	h.mu.RLock()
	defer h.mu.RUnlock()
	return h.ok, h.detail, h.at
}

func main() {
	service := strings.TrimSpace(os.Getenv("MAILCOW_AGENT_SERVICE"))
	if service == "" {
		fmt.Fprintf(os.Stderr, "mailcow-agent: MAILCOW_AGENT_SERVICE is required. Known: %v\n", services.Known())
		os.Exit(2)
	}

	// `mailcow-agent healthcheck` runs the probe once and exits 0/1
	if len(os.Args) > 1 && os.Args[1] == "healthcheck" {
		runHealthcheckOnce(service)
	}

	nodeID := strings.TrimSpace(os.Getenv("MAILCOW_AGENT_NODE_ID"))
	if nodeID == "" {
		h, err := os.Hostname()
		if err != nil {
			log.Fatalf("mailcow-agent: hostname: %v", err)
		}
		nodeID = h
	}

	mainCmd := strings.TrimSpace(os.Getenv("MAILCOW_AGENT_MAIN_CMD"))
	// host-agent has no supervised child; everything else runs one.
	wantsSupervisor := service != "host" && mainCmd != ""

	rdb, err := newRedis()
	if err != nil {
		log.Fatalf("mailcow-agent: redis: %v", err)
	}
	defer rdb.Close()

	// Start the supervised process before serving bus requests — restart/stop
	// handlers assume something is already running.
	var sup *proc.Supervisor
	if wantsSupervisor {
		sup = proc.New(mainCmd)
		if err := sup.Start(); err != nil {
			log.Fatalf("mailcow-agent: start main: %v", err)
		}
	}

	table, err := services.Build(service, sup)
	if err != nil {
		log.Fatalf("mailcow-agent: %v", err)
	}

	// We handle signals ourselves so we can (a) suppress the Go-runtime stack
	// dump on SIGQUIT (php-fpm-alpine's STOPSIGNAL) and (b) remember which
	// signal arrived to forward it to the child on shutdown.
	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	sigCh := make(chan os.Signal, 1)
	signal.Notify(sigCh, syscall.SIGINT, syscall.SIGTERM, syscall.SIGQUIT,
		syscall.SIGHUP, syscall.SIGUSR1, syscall.SIGUSR2)
	defer signal.Stop(sigCh)

	stopSig := atomicSignal{}
	stopSig.Store(syscall.SIGTERM)

	go func() {
		for sig := range sigCh {
			switch sig {
			case syscall.SIGTERM, syscall.SIGINT, syscall.SIGQUIT:
				stopSig.Store(sig.(syscall.Signal))
				log.Printf("mailcow-agent: caught %s, beginning graceful shutdown", sig)
				cancel()
				return
			case syscall.SIGHUP, syscall.SIGUSR1, syscall.SIGUSR2:
				if sup != nil {
					sup.SignalChild(sig)
				}
			}
		}
	}()

	// Initial state is "ok" so the service isn't flagged unhealthy before the
	// first probe has run.
	health := &healthState{ok: true, detail: "", at: time.Now()}
	if table.HealthProbe != nil {
		go runHealthLoop(ctx, table.HealthProbe, health, 10*time.Second)
	}

	hb := registry.Heartbeat{
		Service:   service,
		NodeID:    nodeID,
		Version:   agentVersion,
		StartedAt: time.Now(),
		Image:     os.Getenv("MAILCOW_AGENT_IMAGE"),
		Health:    health,
	}
	go registry.Loop(ctx, rdb, hb, 10*time.Second)

	// cgroup stats for this container. Host metrics come from exec.host-stats.
	pub := stats.NewPublisher(rdb, service, nodeID)
	go pub.Run(ctx, 10*time.Second)

	srv := bus.NewServer(rdb, table, nodeID)
	busErrCh := make(chan error, 1)
	go func() { busErrCh <- srv.Run(ctx) }()

	log.Printf("mailcow-agent: service=%s node=%s ready (commands=%d)", service, nodeID, len(table.Handlers))

	// Exit only on outside termination or fatal bus error. A crashed/stopped
	// child should not tear down the container — the operator may want to
	// issue `start` over the bus afterwards.
	exitCode := 0
	select {
	case <-ctx.Done():
		log.Println("mailcow-agent: shutdown signal received")
	case err := <-busErrCh:
		if err != nil && !errors.Is(err, context.Canceled) {
			log.Printf("mailcow-agent: bus loop exited: %v", err)
			exitCode = 1
		}
	}

	// Graceful shutdown bounded at 35s.
	shutCtx, shutCancel := context.WithTimeout(context.Background(), 35*time.Second)
	defer shutCancel()
	_ = srv.Shutdown(shutCtx)
	_ = registry.Deregister(shutCtx, rdb, service, nodeID)
	if sup != nil {
		// Forward the exact signal we received so the child sees the same
		// shutdown semantics it would without us in front (e.g. SIGQUIT →
		// php-fpm graceful drain).
		if err := sup.StopWithSignal(shutCtx, stopSig.Load()); err != nil {
			log.Printf("mailcow-agent: stop main: %v", err)
		}
	}
	os.Exit(exitCode)
}

// runHealthcheckOnce runs the local probe with a tight deadline and exits 0/1.
// Used by the `healthcheck` sub-command path.
func runHealthcheckOnce(service string) {
	table, err := services.Build(service, nil)
	if err != nil {
		fmt.Fprintln(os.Stderr, "mailcow-agent healthcheck:", err)
		os.Exit(2)
	}
	if table.HealthProbe == nil {
		// Services without a probe are considered healthy.
		os.Exit(0)
	}
	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()
	if err := table.HealthProbe(ctx); err != nil {
		fmt.Fprintln(os.Stderr, "unhealthy:", err)
		os.Exit(1)
	}
	os.Exit(0)
}

// runHealthLoop ticks the probe and updates state. Same probe path as the
// healthcheck sub-command.
func runHealthLoop(ctx context.Context, probe commands.HealthProbe, state *healthState, interval time.Duration) {
	t := time.NewTicker(interval)
	defer t.Stop()
	check := func() {
		pctx, cancel := context.WithTimeout(ctx, 10*time.Second)
		defer cancel()
		if err := probe(pctx); err != nil {
			state.Set(false, err.Error())
		} else {
			state.Set(true, "")
		}
	}
	check()
	for {
		select {
		case <-ctx.Done():
			return
		case <-t.C:
			check()
		}
	}
}

func newRedis() (*redis.Client, error) {
	addr := os.Getenv("REDIS_SLAVEOF_IP")
	port := os.Getenv("REDIS_SLAVEOF_PORT")
	if addr == "" {
		addr = "redis-mailcow"
		port = "6379"
	}
	if port == "" {
		port = "6379"
	}
	pass := os.Getenv("REDISPASS")
	cli := redis.NewClient(&redis.Options{
		Addr:            addr + ":" + port,
		Password:        pass,
		DB:              0,
		MaxRetries:      -1,
		MinRetryBackoff: 200 * time.Millisecond,
		MaxRetryBackoff: 5 * time.Second,
	})
	// Wait up to 2 minutes for Redis to come up before giving up
	deadline := time.Now().Add(2 * time.Minute)
	var lastErr error
	for attempt := 1; time.Now().Before(deadline); attempt++ {
		ctx, cancel := context.WithTimeout(context.Background(), 3*time.Second)
		err := cli.Ping(ctx).Err()
		cancel()
		if err == nil {
			return cli, nil
		}
		lastErr = err
		wait := time.Duration(attempt) * time.Second
		if wait > 10*time.Second {
			wait = 10 * time.Second
		}
		log.Printf("mailcow-agent: waiting for redis %s (attempt %d): %v", addr, attempt, err)
		time.Sleep(wait)
	}
	return nil, fmt.Errorf("ping %s after 2m: %w", addr, lastErr)
}
