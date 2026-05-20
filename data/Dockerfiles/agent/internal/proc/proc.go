// Package proc supervises the service's main process — postfix, dovecot,
// nginx, … — as a child of the agent. It exposes the high-level lifecycle
// verbs (reload/restart/stop/start) used by the per-service command tables.
//
// "reload"  → SIGHUP
// "restart" → SIGTERM, wait, exec again
// "stop"    → SIGTERM, leave stopped
// "start"   → exec again (only if currently stopped)
package proc

import (
	"context"
	"errors"
	"fmt"
	"os"
	"os/exec"
	"sync"
	"syscall"
	"time"
)

// Supervisor wraps a single child process.
type Supervisor struct {
	cmdLine    string // shell command (passed to `sh -c …`)
	stopSignal os.Signal
	stopGrace  time.Duration

	mu       sync.Mutex
	cmd      *exec.Cmd
	stopped  bool
	exitedCh chan struct{}
}

// New constructs a Supervisor. cmdLine is executed via `sh -c` so existing
// docker-entrypoint.sh scripts keep working without quoting headaches.
func New(cmdLine string) *Supervisor {
	return &Supervisor{
		cmdLine:    cmdLine,
		stopSignal: syscall.SIGTERM,
		stopGrace:  30 * time.Second,
	}
}

// Start launches the child process. Returns an error if it cannot be spawned.
// The agent's main() also blocks on Wait() to surface exit status.
func (s *Supervisor) Start() error {
	s.mu.Lock()
	defer s.mu.Unlock()
	if s.cmd != nil && s.cmd.Process != nil && !s.stopped {
		return errors.New("proc: already running")
	}
	// `exec ` prefix tells the shell to replace itself with the command
	// instead of forking and waiting. Without it, sh stays alive as the
	// parent of the real service process, signals from us land on the
	// shell instead of on the service, and SIGHUP for config reloads
	// silently does nothing. With the prefix the supervised PID *is* the
	// service after the script's own `exec "$@"` chains through.
	cmd := exec.Command("/bin/sh", "-c", "exec "+s.cmdLine)
	cmd.Stdout = os.Stdout
	cmd.Stderr = os.Stderr
	cmd.SysProcAttr = &syscall.SysProcAttr{Setpgid: true}
	if err := cmd.Start(); err != nil {
		return fmt.Errorf("proc: start: %w", err)
	}
	s.cmd = cmd
	s.stopped = false
	s.exitedCh = make(chan struct{})
	go func() {
		_ = cmd.Wait()
		close(s.exitedCh)
	}()
	return nil
}

// Wait blocks until the child exits and returns its exit code.
func (s *Supervisor) Wait() int {
	s.mu.Lock()
	exited := s.exitedCh
	cmd := s.cmd
	s.mu.Unlock()
	if exited == nil {
		return -1
	}
	<-exited
	if cmd == nil || cmd.ProcessState == nil {
		return -1
	}
	return cmd.ProcessState.ExitCode()
}

// SignalChild forwards a single signal to the supervised child without
// changing the supervisor's lifecycle state. Used to relay SIGHUP/USR1/USR2
// from the agent's signal handler to the service so operators can still
// `docker compose kill -s HUP postfix-mailcow` and see the expected effect.
func (s *Supervisor) SignalChild(sig os.Signal) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	if s.cmd == nil || s.cmd.Process == nil || s.stopped {
		return errors.New("proc: not running")
	}
	return s.cmd.Process.Signal(sig)
}

// Reload sends SIGHUP. Returns nil if the signal was delivered.
func (s *Supervisor) Reload() error {
	s.mu.Lock()
	defer s.mu.Unlock()
	if s.cmd == nil || s.cmd.Process == nil || s.stopped {
		return errors.New("proc: not running")
	}
	return s.cmd.Process.Signal(syscall.SIGHUP)
}

// Stop sends the configured stop signal and waits for the process to exit
// (bounded by stopGrace). Marks the supervisor as stopped — Start must be
// called again to relaunch.
func (s *Supervisor) Stop(ctx context.Context) error {
	return s.StopWithSignal(ctx, s.stopSignal)
}

// StopWithSignal is like Stop but lets the caller override the stop signal.
// Used by main() to forward whatever signal Docker sent us (SIGTERM for
// most containers, SIGQUIT for php-fpm-alpine which uses SIGQUIT for
// graceful shutdown) so the child gets the same signal semantics it would
// receive without the agent in front of it.
func (s *Supervisor) StopWithSignal(ctx context.Context, sig os.Signal) error {
	s.mu.Lock()
	cmd := s.cmd
	exited := s.exitedCh
	if cmd == nil || cmd.Process == nil {
		s.mu.Unlock()
		return nil
	}
	s.stopped = true
	s.mu.Unlock()

	sysSig, ok := sig.(syscall.Signal)
	if !ok {
		sysSig = syscall.SIGTERM
	}
	pgid, err := syscall.Getpgid(cmd.Process.Pid)
	if err == nil {
		_ = syscall.Kill(-pgid, sysSig)
	} else {
		_ = cmd.Process.Signal(sysSig)
	}

	timer := time.NewTimer(s.stopGrace)
	defer timer.Stop()
	select {
	case <-exited:
		return nil
	case <-timer.C:
		// Last resort: SIGKILL the whole process group.
		if pgid, err := syscall.Getpgid(cmd.Process.Pid); err == nil {
			_ = syscall.Kill(-pgid, syscall.SIGKILL)
		} else {
			_ = cmd.Process.Kill()
		}
		<-exited
		return errors.New("proc: forced kill after grace period")
	case <-ctx.Done():
		return ctx.Err()
	}
}

// Restart performs Stop+Start using the supervisor's default stop signal.
// Different from a Docker-initiated shutdown: here it's an explicit "restart
// this service" command, so we want the standard SIGTERM semantics.
func (s *Supervisor) Restart(ctx context.Context) error {
	if err := s.Stop(ctx); err != nil {
		return err
	}
	return s.Start()
}

// IsRunning reports whether the supervised child is currently alive (started
// and not yet exited or stopped).
func (s *Supervisor) IsRunning() bool {
	s.mu.Lock()
	defer s.mu.Unlock()
	if s.stopped || s.cmd == nil || s.cmd.Process == nil {
		return false
	}
	// exitedCh is closed when the child exits. Non-blocking read.
	select {
	case <-s.exitedCh:
		return false
	default:
		return true
	}
}

// WaitStable blocks for `settle` and returns nil if the supervised child is
// still running at the end, otherwise an error describing the exit. Used by
// the `restart` command to give the operator real "did it come back up"
// feedback instead of an immediate OK.
func (s *Supervisor) WaitStable(ctx context.Context, settle time.Duration) error {
	s.mu.Lock()
	exited := s.exitedCh
	s.mu.Unlock()
	if exited == nil {
		return errors.New("proc: not running")
	}
	select {
	case <-exited:
		// Child died within the settle window.
		s.mu.Lock()
		cmd := s.cmd
		s.mu.Unlock()
		code := -1
		if cmd != nil && cmd.ProcessState != nil {
			code = cmd.ProcessState.ExitCode()
		}
		return fmt.Errorf("proc: child exited within settle window (code=%d)", code)
	case <-time.After(settle):
		return nil
	case <-ctx.Done():
		return ctx.Err()
	}
}

// Forward installs a signal forwarder: SIGINT/SIGTERM/SIGHUP/SIGUSR1/SIGUSR2
// received by the agent are propagated to the child. Returns a cancel func
// to release the handler.
func (s *Supervisor) Forward(signals ...os.Signal) func() {
	ch := make(chan os.Signal, len(signals)+1)
	signalNotify(ch, signals...)
	done := make(chan struct{})
	go func() {
		for {
			select {
			case <-done:
				return
			case sig := <-ch:
				s.mu.Lock()
				cmd := s.cmd
				s.mu.Unlock()
				if cmd != nil && cmd.Process != nil {
					_ = cmd.Process.Signal(sig)
				}
				if sig == syscall.SIGTERM || sig == syscall.SIGINT {
					// On terminal signals propagate and let main exit.
					return
				}
			}
		}
	}()
	return func() {
		close(done)
		signalStop(ch)
	}
}
