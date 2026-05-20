package services

import (
	"context"
	"time"

	"github.com/mailcow/mailcow-dockerized/agent/internal/commands"
	"github.com/mailcow/mailcow-dockerized/agent/internal/proc"
)

func init() { Register("nginx", buildNginx) }

func nginxHealthProbe(ctx context.Context) error {
	if err := probeShell(ctx, 3*time.Second, "nginx", "-t"); err != nil {
		return err
	}
	return probeTCP("127.0.0.1:8081", 2*time.Second)
}

func buildNginx(sup *proc.Supervisor) *commands.Table {
	t := commands.New("nginx")
	t.HealthProbe = nginxHealthProbe
	t.Register("reload", func(ctx context.Context, _ map[string]any) (any, error) {
		r, err := commands.Run(ctx, commands.RunOptions{}, "nginx", "-s", "reload")
		return nil, asError(r, err)
	})
	addLifecycleExceptReload(t, sup)
	t.Register("exec.test-config", func(ctx context.Context, _ map[string]any) (any, error) {
		r, err := commands.Run(ctx, commands.RunOptions{}, "nginx", "-t")
		if err != nil {
			return nil, err
		}
		return map[string]any{
			"ok":     r.ExitCode == 0,
			"output": r.Stderr + r.Stdout,
		}, nil
	})
	return t
}
