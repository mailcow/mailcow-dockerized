package services

import (
	"context"
	"encoding/json"
	"strings"
	"time"

	"github.com/mailcow/mailcow-dockerized/agent/internal/commands"
	"github.com/mailcow/mailcow-dockerized/agent/internal/proc"
)

func init() { Register("postfix", buildPostfix) }

// notFoundFragments are substrings emitted by postsuper/postqueue when the
// requested queue id doesn't live on this node. Broadcast handlers map them
// to commands.ErrNotFound so the backend can count partial success.
var notFoundFragments = []string{
	"No such file or directory",
	"no such file",
	"unknown",
}

func postfixHealthProbe(ctx context.Context) error {
	if err := probeSMTPGreeting("127.0.0.1:25", 3*time.Second); err != nil {
		return err
	}
	return probeShell(ctx, 5*time.Second, "postfix", "status")
}

func buildPostfix(sup *proc.Supervisor) *commands.Table {
	t := commands.New("postfix")
	t.HealthProbe = postfixHealthProbe

	// Override generic reload — `postfix reload` is the canonical operation,
	// not SIGHUP-to-supervisord (which would just rotate logs).
	t.Register("reload", func(ctx context.Context, _ map[string]any) (any, error) {
		r, err := commands.Run(ctx, commands.RunOptions{}, "postfix", "reload")
		return nil, asError(r, err)
	})
	// Lifecycle: stop/start/restart still go through the supervisor.
	if sup != nil {
		t.Register("restart", func(ctx context.Context, _ map[string]any) (any, error) {
			return nil, sup.Restart(ctx)
		})
		t.Register("stop", func(ctx context.Context, _ map[string]any) (any, error) {
			return nil, sup.Stop(ctx)
		})
		t.Register("start", func(ctx context.Context, _ map[string]any) (any, error) {
			return nil, sup.Start()
		})
	}

	t.Register("exec.mailq", func(ctx context.Context, _ map[string]any) (any, error) {
		r, err := commands.Run(ctx, commands.RunOptions{OutputCap: 8 << 20}, "postqueue", "-j")
		if err != nil {
			return nil, err
		}
		if r.ExitCode != 0 {
			return nil, &runError{msg: "postqueue failed: " + r.Stderr}
		}
		// postqueue -j prints one JSON object per line.
		entries := make([]map[string]any, 0)
		for _, line := range strings.Split(strings.TrimSpace(r.Stdout), "\n") {
			line = strings.TrimSpace(line)
			if line == "" {
				continue
			}
			var obj map[string]any
			if err := json.Unmarshal([]byte(line), &obj); err == nil {
				entries = append(entries, obj)
			}
		}
		return map[string]any{"queue": entries}, nil
	})

	t.Register("exec.flush-queue", func(ctx context.Context, _ map[string]any) (any, error) {
		r, err := commands.Run(ctx, commands.RunOptions{}, "postqueue", "-f")
		return nil, asError(r, err)
	})

	t.Register("exec.delete-from-queue", func(ctx context.Context, args map[string]any) (any, error) {
		qid, err := commands.ArgString(args, "queue_id")
		if err != nil {
			return nil, err
		}
		r, err := commands.Run(ctx, commands.RunOptions{}, "postsuper", "-d", qid)
		return nil, asNotFoundOrError(r, err)
	})

	t.Register("exec.hold-queue", func(ctx context.Context, args map[string]any) (any, error) {
		qid, err := commands.ArgString(args, "queue_id")
		if err != nil {
			return nil, err
		}
		r, err := commands.Run(ctx, commands.RunOptions{}, "postsuper", "-h", qid)
		return nil, asNotFoundOrError(r, err)
	})

	t.Register("exec.unhold-queue", func(ctx context.Context, args map[string]any) (any, error) {
		qid, err := commands.ArgString(args, "queue_id")
		if err != nil {
			return nil, err
		}
		r, err := commands.Run(ctx, commands.RunOptions{}, "postsuper", "-H", qid)
		return nil, asNotFoundOrError(r, err)
	})

	t.Register("exec.deliver-now", func(ctx context.Context, args map[string]any) (any, error) {
		qid, err := commands.ArgString(args, "queue_id")
		if err != nil {
			return nil, err
		}
		r, err := commands.Run(ctx, commands.RunOptions{}, "postqueue", "-i", qid)
		return nil, asNotFoundOrError(r, err)
	})

	t.Register("exec.cat-queue", func(ctx context.Context, args map[string]any) (any, error) {
		qid, err := commands.ArgString(args, "queue_id")
		if err != nil {
			return nil, err
		}
		r, err := commands.Run(ctx, commands.RunOptions{OutputCap: 2 << 20}, "postcat", "-q", qid)
		if err != nil {
			return nil, err
		}
		if r.ExitCode != 0 {
			if matchesAny(r.Stderr, notFoundFragments) {
				return nil, commands.ErrNotFound
			}
			return nil, &runError{msg: "postcat failed: " + r.Stderr}
		}
		return map[string]any{"body": r.Stdout}, nil
	})

	t.Register("exec.super-delete", func(ctx context.Context, _ map[string]any) (any, error) {
		r, err := commands.Run(ctx, commands.RunOptions{}, "postsuper", "-d", "ALL")
		return nil, asError(r, err)
	})

	return t
}
