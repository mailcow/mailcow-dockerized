// Package services registers per-service command tables. The agent selects
// the right table at startup via MAILCOW_AGENT_SERVICE.
//
// A service "builder" receives a Supervisor for lifecycle commands; services
// that don't supervise a main process (currently just "host") pass nil and
// the generic lifecycle commands are skipped.
package services

import (
	"context"
	"fmt"
	"time"

	"github.com/mailcow/mailcow-dockerized/agent/internal/commands"
	"github.com/mailcow/mailcow-dockerized/agent/internal/proc"
)

// Builder constructs a command table for a service. sup may be nil for
// services without a supervised main process.
type Builder func(sup *proc.Supervisor) *commands.Table

var registry = map[string]Builder{}

// Register installs a builder for a service name. Called from init() in each
// per-service file.
func Register(service string, b Builder) {
	if _, dup := registry[service]; dup {
		panic("services: duplicate registration for " + service)
	}
	registry[service] = b
}

// Build returns the table for service, or an error if no builder exists.
func Build(service string, sup *proc.Supervisor) (*commands.Table, error) {
	b, ok := registry[service]
	if !ok {
		return nil, fmt.Errorf("services: unknown service %q (set MAILCOW_AGENT_SERVICE correctly)", service)
	}
	return b(sup), nil
}

// Known returns the list of registered service names (sorted-ish, depends on
// map iteration — for help output only).
func Known() []string {
	out := make([]string, 0, len(registry))
	for k := range registry {
		out = append(out, k)
	}
	return out
}

// restartSettle is how long we wait after a Start to verify the new child
// didn't immediately crash. Gives the operator real "did the service come
// back up?" feedback instead of an instant OK that hides flapping services.
const restartSettle = 3 * time.Second

// addLifecycle wires reload/restart/stop/start onto t backed by sup. Services
// override these (e.g. postfix overrides reload to run `postfix reload`).
func addLifecycle(t *commands.Table, sup *proc.Supervisor) {
	if sup == nil {
		return
	}
	t.Register("reload", func(ctx context.Context, _ map[string]any) (any, error) {
		return nil, sup.Reload()
	})
	t.Register("restart", func(ctx context.Context, _ map[string]any) (any, error) {
		if err := sup.Restart(ctx); err != nil {
			return nil, err
		}
		if err := sup.WaitStable(ctx, restartSettle); err != nil {
			return nil, err
		}
		return map[string]any{"status": "restarted", "settled_ms": int(restartSettle / time.Millisecond)}, nil
	})
	t.Register("stop", func(ctx context.Context, _ map[string]any) (any, error) {
		return nil, sup.Stop(ctx)
	})
	t.Register("start", func(ctx context.Context, _ map[string]any) (any, error) {
		if err := sup.Start(); err != nil {
			return nil, err
		}
		if err := sup.WaitStable(ctx, restartSettle); err != nil {
			return nil, err
		}
		return map[string]any{"status": "started", "settled_ms": int(restartSettle / time.Millisecond)}, nil
	})
}
