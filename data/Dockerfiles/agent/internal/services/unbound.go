package services

import (
	"context"
	"time"

	"github.com/mailcow/mailcow-dockerized/agent/internal/commands"
	"github.com/mailcow/mailcow-dockerized/agent/internal/proc"
)

func init() { Register("unbound", buildUnbound) }

func unboundHealthProbe(ctx context.Context) error {
	return probeShell(ctx, 3*time.Second, "dig", "+time=2", "+tries=1", "@127.0.0.1", "mailcow.email", "A")
}

func buildUnbound(sup *proc.Supervisor) *commands.Table {
	t := commands.New("unbound")
	t.HealthProbe = unboundHealthProbe
	addLifecycle(t, sup)
	t.Register("exec.flush-cache", func(ctx context.Context, _ map[string]any) (any, error) {
		r, err := commands.Run(ctx, commands.RunOptions{}, "unbound-control", "flush_zone", ".")
		return nil, asError(r, err)
	})
	return t
}
