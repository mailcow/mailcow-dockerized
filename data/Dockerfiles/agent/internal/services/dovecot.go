package services

import (
	"context"
	"fmt"
	"net"
	"os"
	"path/filepath"
	"strings"
	"syscall"
	"time"

	"github.com/mailcow/mailcow-dockerized/agent/internal/commands"
	"github.com/mailcow/mailcow-dockerized/agent/internal/proc"
)

func init() { Register("dovecot", buildDovecot) }

const vmailRoot = "/var/vmail"

func dovecotHealthProbe(ctx context.Context) error {
	// IMAP greeting on :143 — must be "* OK ..."
	conn, err := net.DialTimeout("tcp", "127.0.0.1:143", 3*time.Second)
	if err != nil {
		return err
	}
	defer conn.Close()
	buf := make([]byte, 64)
	_ = conn.SetReadDeadline(time.Now().Add(3 * time.Second))
	n, err := conn.Read(buf)
	if err != nil {
		return fmt.Errorf("read greeting: %w", err)
	}
	greeting := string(buf[:n])
	if !strings.HasPrefix(greeting, "* OK") {
		return fmt.Errorf("unexpected greeting: %s", strings.TrimSpace(greeting))
	}
	return nil
}

func buildDovecot(sup *proc.Supervisor) *commands.Table {
	t := commands.New("dovecot")
	t.HealthProbe = dovecotHealthProbe

	// `dovecot reload` re-reads config without restarting the master process.
	t.Register("reload", func(ctx context.Context, _ map[string]any) (any, error) {
		r, err := commands.Run(ctx, commands.RunOptions{}, "dovecot", "reload")
		return nil, asError(r, err)
	})
	addLifecycleExceptReload(t, sup)

	t.Register("exec.fts-rescan", func(ctx context.Context, args map[string]any) (any, error) {
		user := commands.ArgStringOpt(args, "user", "")
		argv := []string{"doveadm", "fts", "rescan"}
		if user != "" {
			argv = append(argv, "-u", user)
		} else {
			argv = append(argv, "-A")
		}
		r, err := commands.Run(ctx, commands.RunOptions{}, argv...)
		return nil, asError(r, err)
	})

	t.Register("exec.sieve-list", func(ctx context.Context, args map[string]any) (any, error) {
		user, err := commands.ArgString(args, "user")
		if err != nil {
			return nil, err
		}
		r, err := commands.Run(ctx, commands.RunOptions{}, "doveadm", "sieve", "list", "-u", user)
		if err != nil {
			return nil, err
		}
		if r.ExitCode != 0 {
			return nil, &runError{msg: strings.TrimSpace(r.Stderr)}
		}
		scripts := splitNonEmpty(r.Stdout)
		return map[string]any{"scripts": scripts}, nil
	})

	t.Register("exec.sieve-print", func(ctx context.Context, args map[string]any) (any, error) {
		user, err := commands.ArgString(args, "user")
		if err != nil {
			return nil, err
		}
		script, err := commands.ArgString(args, "script")
		if err != nil {
			return nil, err
		}
		r, err := commands.Run(ctx, commands.RunOptions{OutputCap: 1 << 20}, "doveadm", "sieve", "get", "-u", user, script)
		if err != nil {
			return nil, err
		}
		if r.ExitCode != 0 {
			return nil, &runError{msg: strings.TrimSpace(r.Stderr)}
		}
		return map[string]any{"body": r.Stdout}, nil
	})

	t.Register("exec.acl-get", func(ctx context.Context, args map[string]any) (any, error) {
		user, err := commands.ArgString(args, "user")
		if err != nil {
			return nil, err
		}
		// First enumerate mailboxes, then collect ACLs per mailbox.
		boxes, err := commands.Run(ctx, commands.RunOptions{}, "doveadm", "mailbox", "list", "-u", user)
		if err != nil {
			return nil, err
		}
		if boxes.ExitCode != 0 {
			return nil, &runError{msg: strings.TrimSpace(boxes.Stderr)}
		}
		out := []map[string]any{}
		for _, mbx := range splitNonEmpty(boxes.Stdout) {
			r, err := commands.Run(ctx, commands.RunOptions{}, "doveadm", "acl", "get", "-u", user, mbx)
			if err != nil || r.ExitCode != 0 {
				continue
			}
			for _, line := range strings.Split(strings.TrimSpace(r.Stdout), "\n") {
				line = strings.TrimSpace(line)
				if line == "" || strings.HasPrefix(line, "ID") {
					continue
				}
				fields := strings.Fields(line)
				if len(fields) >= 2 {
					out = append(out, map[string]any{
						"mailbox":    mbx,
						"identifier": fields[0],
						"rights":     strings.Join(fields[1:], " "),
					})
				}
			}
		}
		return map[string]any{"acls": out}, nil
	})

	t.Register("exec.acl-set", func(ctx context.Context, args map[string]any) (any, error) {
		user, err := commands.ArgString(args, "user")
		if err != nil {
			return nil, err
		}
		mailbox, err := commands.ArgString(args, "mailbox")
		if err != nil {
			return nil, err
		}
		identifier, err := commands.ArgString(args, "identifier")
		if err != nil {
			return nil, err
		}
		rights, err := commands.ArgString(args, "rights")
		if err != nil {
			return nil, err
		}
		r, err := commands.Run(ctx, commands.RunOptions{}, "doveadm", "acl", "set", "-u", user, mailbox, identifier, rights)
		return nil, asError(r, err)
	})

	t.Register("exec.acl-delete", func(ctx context.Context, args map[string]any) (any, error) {
		user, err := commands.ArgString(args, "user")
		if err != nil {
			return nil, err
		}
		mailbox, err := commands.ArgString(args, "mailbox")
		if err != nil {
			return nil, err
		}
		identifier, err := commands.ArgString(args, "identifier")
		if err != nil {
			return nil, err
		}
		r, err := commands.Run(ctx, commands.RunOptions{}, "doveadm", "acl", "delete", "-u", user, mailbox, identifier)
		return nil, asError(r, err)
	})

	t.Register("exec.maildir-cleanup", func(ctx context.Context, args map[string]any) (any, error) {
		maildir, err := commands.ArgString(args, "maildir")
		if err != nil {
			return nil, err
		}
		if err := assertSafeMaildirPath(maildir); err != nil {
			return nil, err
		}
		src := filepath.Join(vmailRoot, maildir)
		dst := filepath.Join(vmailRoot, "_garbage", maildir+"_"+nowStamp())
		if _, err := os.Stat(src); os.IsNotExist(err) {
			return nil, commands.ErrNotFound
		}
		if err := os.MkdirAll(filepath.Dir(dst), 0o770); err != nil {
			return nil, err
		}
		return nil, os.Rename(src, dst)
	})

	t.Register("exec.df", func(ctx context.Context, args map[string]any) (any, error) {
		dir := commands.ArgStringOpt(args, "dir", "/var/vmail")
		var st syscall.Statfs_t
		if err := syscall.Statfs(dir, &st); err != nil {
			return nil, err
		}
		size := uint64(st.Blocks) * uint64(st.Bsize)
		free := uint64(st.Bavail) * uint64(st.Bsize)
		used := size - free
		pct := 0
		if size > 0 {
			pct = int(float64(used) / float64(size) * 100)
		}
		// Format: Filesystem,Size,Used,Avail,Use%,Mounted-on
		return fmt.Sprintf("%s,%s,%s,%s,%d%%,%s",
			"local", humanBytes(size), humanBytes(used), humanBytes(free), pct, dir), nil
	})

	t.Register("exec.maildir-move", func(ctx context.Context, args map[string]any) (any, error) {
		from, err := commands.ArgString(args, "from")
		if err != nil {
			return nil, err
		}
		to, err := commands.ArgString(args, "to")
		if err != nil {
			return nil, err
		}
		if err := assertSafeMaildirPath(from); err != nil {
			return nil, err
		}
		if err := assertSafeMaildirPath(to); err != nil {
			return nil, err
		}
		src := filepath.Join(vmailRoot, from)
		dst := filepath.Join(vmailRoot, to)
		if _, err := os.Stat(src); os.IsNotExist(err) {
			return nil, commands.ErrNotFound
		}
		if err := os.MkdirAll(filepath.Dir(dst), 0o770); err != nil {
			return nil, err
		}
		return nil, os.Rename(src, dst)
	})

	return t
}

// addLifecycleExceptReload wires restart/stop/start without overriding reload,
// which postfix/dovecot define themselves (canonical CLI command).
func addLifecycleExceptReload(t *commands.Table, sup *proc.Supervisor) {
	if sup == nil {
		return
	}
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

func splitNonEmpty(s string) []string {
	out := []string{}
	for _, line := range strings.Split(strings.TrimSpace(s), "\n") {
		line = strings.TrimSpace(line)
		if line != "" {
			out = append(out, line)
		}
	}
	return out
}

// assertSafeMaildirPath blocks path traversal and absolute paths — relative
// names under /var/vmail only.
func assertSafeMaildirPath(p string) error {
	if p == "" || strings.HasPrefix(p, "/") || strings.Contains(p, "..") {
		return &validationErr{msg: "unsafe maildir path"}
	}
	return nil
}

type validationErr struct{ msg string }

func (e *validationErr) Error() string        { return e.msg }
func (e *validationErr) Is(target error) bool { return target == commands.ErrValidation }

// humanBytes renders a byte count in `df -H` style (1000-based units).
func humanBytes(n uint64) string {
	const unit = 1000
	if n < unit {
		return fmt.Sprintf("%dB", n)
	}
	div, exp := uint64(unit), 0
	for x := n / unit; x >= unit; x /= unit {
		div *= unit
		exp++
	}
	return fmt.Sprintf("%.1f%c", float64(n)/float64(div), "KMGTPE"[exp])
}
