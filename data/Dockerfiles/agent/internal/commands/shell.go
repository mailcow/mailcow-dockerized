package commands

import (
	"bytes"
	"context"
	"fmt"
	"os/exec"
)

// RunOptions configures a single Run invocation.
type RunOptions struct {
	// Stdin, if non-nil, is written to the process stdin.
	Stdin []byte
	// CombinedOutputCap limits the captured output (truncated at the end).
	// 0 means unlimited. The agent uses ~1 MiB for cat-queue, smaller for
	// status-style commands.
	OutputCap int
}

// RunResult is what every shell-style command returns.
type RunResult struct {
	Stdout   string `json:"stdout,omitempty"`
	Stderr   string `json:"stderr,omitempty"`
	ExitCode int    `json:"exit_code"`
}

// Run executes argv[0] argv[1:] under ctx (the bus deadline). It does not
// translate exit codes to errors — callers inspect r.ExitCode themselves so
// they can map e.g. "queue id not found" exit codes to ErrNotFound.
func Run(ctx context.Context, opts RunOptions, argv ...string) (*RunResult, error) {
	if len(argv) == 0 {
		return nil, fmt.Errorf("commands.Run: empty argv")
	}
	cmd := exec.CommandContext(ctx, argv[0], argv[1:]...)
	var stdout, stderr bytes.Buffer
	cmd.Stdout = &stdout
	cmd.Stderr = &stderr
	if opts.Stdin != nil {
		cmd.Stdin = bytes.NewReader(opts.Stdin)
	}
	err := cmd.Run()

	out := stdout.String()
	errOut := stderr.String()
	if opts.OutputCap > 0 {
		if len(out) > opts.OutputCap {
			out = out[:opts.OutputCap] + "\n…(truncated)"
		}
		if len(errOut) > opts.OutputCap {
			errOut = errOut[:opts.OutputCap] + "\n…(truncated)"
		}
	}

	exit := 0
	if exitErr, ok := err.(*exec.ExitError); ok {
		exit = exitErr.ExitCode()
		err = nil
	}
	return &RunResult{Stdout: out, Stderr: errOut, ExitCode: exit}, err
}
