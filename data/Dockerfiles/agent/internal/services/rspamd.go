package services

import (
	"context"
	"os"
	"path/filepath"
	"strings"
	"time"

	"github.com/mailcow/mailcow-dockerized/agent/internal/commands"
	"github.com/mailcow/mailcow-dockerized/agent/internal/proc"
)

func init() { Register("rspamd", buildRspamd) }

func rspamdHealthProbe(ctx context.Context) error {
	return probeHTTP(ctx, "http://127.0.0.1:11334/ping", 3*time.Second)
}

// Override file rspamd reads on startup for the controller's enable_password.
const rspamdWorkerPasswordPath = "/etc/rspamd/override.d/worker-controller-password.inc"

func buildRspamd(sup *proc.Supervisor) *commands.Table {
	t := commands.New("rspamd")
	t.HealthProbe = rspamdHealthProbe
	addLifecycle(t, sup)

	t.Register("exec.set-worker-password", func(ctx context.Context, args map[string]any) (any, error) {
		password, err := commands.ArgString(args, "password")
		if err != nil {
			return nil, err
		}
		// rspamadm pw -e -p <pw> writes the hashed value to stdout.
		r, err := commands.Run(ctx, commands.RunOptions{}, "rspamadm", "pw", "-e", "-p", password)
		if err != nil {
			return nil, err
		}
		if r.ExitCode != 0 {
			return nil, &runError{msg: "rspamadm pw failed: " + strings.TrimSpace(r.Stderr)}
		}
		hash := strings.TrimSpace(r.Stdout)
		// rspamd distinguishes `password` (read-only access to the controller)
		// from `enable_password` (write access — restart, settings, learn).
		content := "enable_password = \"" + hash + "\";\n"
		if err := os.MkdirAll(filepath.Dir(rspamdWorkerPasswordPath), 0o755); err != nil {
			return nil, err
		}
		if err := os.WriteFile(rspamdWorkerPasswordPath, []byte(content), 0o644); err != nil {
			return nil, err
		}
		// Must do a full re-fork of workers (SIGHUP to rspamd master), not
		// `rspamadm control reload`
		if sup != nil {
			return nil, sup.Reload()
		}
		return nil, nil
	})

	t.Register("exec.relearn-spam", func(ctx context.Context, args map[string]any) (any, error) {
		path, err := commands.ArgString(args, "file")
		if err != nil {
			return nil, err
		}
		data, err := os.ReadFile(path)
		if err != nil {
			return nil, err
		}
		r, err := commands.Run(ctx, commands.RunOptions{Stdin: data}, "rspamc", "learn_spam")
		return nil, asError(r, err)
	})

	t.Register("exec.relearn-ham", func(ctx context.Context, args map[string]any) (any, error) {
		path, err := commands.ArgString(args, "file")
		if err != nil {
			return nil, err
		}
		data, err := os.ReadFile(path)
		if err != nil {
			return nil, err
		}
		r, err := commands.Run(ctx, commands.RunOptions{Stdin: data}, "rspamc", "learn_ham")
		return nil, asError(r, err)
	})

	return t
}
