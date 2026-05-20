package services

import (
	"bufio"
	"context"
	"errors"
	"fmt"
	"net"
	"net/http"
	"strings"
	"time"

	"github.com/mailcow/mailcow-dockerized/agent/internal/commands"
)

// probeTCP opens a TCP connection to addr within timeout. Returns nil if the
// port accepts a connection, otherwise the dial error.
func probeTCP(addr string, timeout time.Duration) error {
	conn, err := net.DialTimeout("tcp", addr, timeout)
	if err != nil {
		return err
	}
	_ = conn.Close()
	return nil
}

// probeSMTPGreeting connects to addr and reads the SMTP greeting line. The
// service is considered healthy if the line starts with "220".
func probeSMTPGreeting(addr string, timeout time.Duration) error {
	conn, err := net.DialTimeout("tcp", addr, timeout)
	if err != nil {
		return err
	}
	defer conn.Close()
	_ = conn.SetReadDeadline(time.Now().Add(timeout))
	line, err := bufio.NewReader(conn).ReadString('\n')
	if err != nil {
		return fmt.Errorf("read greeting: %w", err)
	}
	if !strings.HasPrefix(line, "220") {
		return fmt.Errorf("unexpected greeting: %s", strings.TrimSpace(line))
	}
	return nil
}

// probeHTTP issues a GET to url, checks for a 2xx status.
func probeHTTP(ctx context.Context, url string, timeout time.Duration) error {
	cctx, cancel := context.WithTimeout(ctx, timeout)
	defer cancel()
	req, err := http.NewRequestWithContext(cctx, http.MethodGet, url, nil)
	if err != nil {
		return err
	}
	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		return fmt.Errorf("http %s", resp.Status)
	}
	return nil
}

// probeShell runs argv with a timeout and returns nil if exit code is 0.
func probeShell(ctx context.Context, timeout time.Duration, argv ...string) error {
	cctx, cancel := context.WithTimeout(ctx, timeout)
	defer cancel()
	r, err := commands.Run(cctx, commands.RunOptions{}, argv...)
	if err != nil {
		return err
	}
	if r.ExitCode != 0 {
		msg := strings.TrimSpace(r.Stderr)
		if msg == "" {
			msg = fmt.Sprintf("exit %d", r.ExitCode)
		}
		return errors.New(msg)
	}
	return nil
}
