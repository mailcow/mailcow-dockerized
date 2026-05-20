package services

import (
	"context"
	"time"

	"github.com/mailcow/mailcow-dockerized/agent/internal/commands"
	"github.com/mailcow/mailcow-dockerized/agent/internal/proc"
)

func init() { Register("sogo", buildSogo) }

func sogoHealthProbe(ctx context.Context) error {
	return probeHTTP(ctx, "http://127.0.0.1:20000/SOGo.index/", 3*time.Second)
}

func buildSogo(sup *proc.Supervisor) *commands.Table {
	t := commands.New("sogo")
	t.HealthProbe = sogoHealthProbe
	addLifecycle(t, sup)

	t.Register("exec.rename-user", func(ctx context.Context, args map[string]any) (any, error) {
		oldName, err := commands.ArgString(args, "old")
		if err != nil {
			return nil, err
		}
		newName, err := commands.ArgString(args, "new")
		if err != nil {
			return nil, err
		}
		r, err := commands.Run(ctx, commands.RunOptions{}, "sogo-tool", "rename-user", oldName, newName)
		return nil, asError(r, err)
	})

	return t
}
