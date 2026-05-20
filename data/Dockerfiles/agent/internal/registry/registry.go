// Package registry publishes per-node heartbeats to Redis so the backend can
// enumerate live containers. Two keys per service:
//
//	ZSET  mailcow.nodes.<service>          score=unix_ts member=node_id
//	HASH  mailcow.node.<service>.<node_id> { version, started_at, image, health* }
//
// Both keys have a 30s TTL refreshed every 10s. Deregister clears them on
// graceful shutdown.
package registry

import (
	"context"
	"fmt"
	"strconv"
	"time"

	"github.com/redis/go-redis/v9"
)

// HealthSnapshotter returns the latest health probe result so the heartbeat
// can attach it to each tick. Implemented by main.healthState.
type HealthSnapshotter interface {
	Snapshot() (ok bool, detail string, at time.Time)
}

// Heartbeat carries the metadata published with every refresh.
type Heartbeat struct {
	Service   string
	NodeID    string
	Version   string
	StartedAt time.Time
	Image     string
	Health    HealthSnapshotter // optional; nil → omit health fields
}

func nodesKey(service string) string      { return "mailcow.nodes." + service }
func nodeKey(service, node string) string { return "mailcow.node." + service + "." + node }

// Publish writes one heartbeat tick. Callers run this in a loop.
func Publish(ctx context.Context, rdb *redis.Client, h Heartbeat) error {
	now := time.Now().Unix()
	fields := map[string]any{
		"version":    h.Version,
		"started_at": h.StartedAt.UTC().Format(time.RFC3339),
		"image":      h.Image,
		"node_id":    h.NodeID,
		"service":    h.Service,
		"updated_at": strconv.FormatInt(now, 10),
	}
	if h.Health != nil {
		ok, detail, at := h.Health.Snapshot()
		if ok {
			fields["health"] = "ok"
		} else {
			fields["health"] = "fail"
		}
		fields["health_detail"] = detail
		fields["health_at"] = strconv.FormatInt(at.Unix(), 10)
	}
	pipe := rdb.Pipeline()
	pipe.ZAdd(ctx, nodesKey(h.Service), redis.Z{Score: float64(now), Member: h.NodeID})
	pipe.Expire(ctx, nodesKey(h.Service), 5*time.Minute)
	pipe.HSet(ctx, nodeKey(h.Service, h.NodeID), fields)
	pipe.Expire(ctx, nodeKey(h.Service, h.NodeID), 30*time.Second)
	_, err := pipe.Exec(ctx)
	if err != nil {
		return fmt.Errorf("registry: heartbeat exec: %w", err)
	}
	return nil
}

// Deregister removes the node from the ZSET and deletes its detail hash.
// Called on graceful shutdown so the dashboard reflects intentional stops
// immediately rather than waiting for TTL.
func Deregister(ctx context.Context, rdb *redis.Client, service, nodeID string) error {
	pipe := rdb.Pipeline()
	pipe.ZRem(ctx, nodesKey(service), nodeID)
	pipe.Del(ctx, nodeKey(service, nodeID))
	_, err := pipe.Exec(ctx)
	return err
}

// Loop runs Publish on a ticker until ctx is done. It is the typical caller.
func Loop(ctx context.Context, rdb *redis.Client, h Heartbeat, interval time.Duration) {
	// Publish once immediately so the dashboard sees us right away.
	_ = Publish(ctx, rdb, h)
	t := time.NewTicker(interval)
	defer t.Stop()
	for {
		select {
		case <-ctx.Done():
			return
		case <-t.C:
			_ = Publish(ctx, rdb, h)
		}
	}
}
