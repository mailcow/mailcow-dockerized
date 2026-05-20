package services

import (
	"strings"

	"github.com/mailcow/mailcow-dockerized/agent/internal/commands"
)

// runError is what we return when a shell command exited non-zero but the
// failure is not a "target not found" case. The bus maps it to
// ErrCodeInternal.
type runError struct{ msg string }

func (e *runError) Error() string { return e.msg }

// asError converts a (RunResult, err) pair from commands.Run into a single
// error: pre-exec error → return as-is; non-zero exit → wrap stderr.
func asError(r *commands.RunResult, err error) error {
	if err != nil {
		return err
	}
	if r.ExitCode != 0 {
		msg := strings.TrimSpace(r.Stderr)
		if msg == "" {
			msg = "command exited " + itoa(r.ExitCode)
		}
		return &runError{msg: msg}
	}
	return nil
}

// asNotFoundOrError is the variant for queue/mailbox operations that may fail
// because the target doesn't live on this node. Maps known stderr fragments
// to commands.ErrNotFound so broadcast aggregation works.
func asNotFoundOrError(r *commands.RunResult, err error) error {
	if err != nil {
		return err
	}
	if r.ExitCode == 0 {
		return nil
	}
	if matchesAny(r.Stderr, notFoundFragments) {
		return commands.ErrNotFound
	}
	return &runError{msg: strings.TrimSpace(r.Stderr)}
}

func matchesAny(haystack string, fragments []string) bool {
	for _, f := range fragments {
		if strings.Contains(haystack, f) {
			return true
		}
	}
	return false
}

func itoa(i int) string {
	// avoid strconv import for a one-shot; small ints only
	if i == 0 {
		return "0"
	}
	neg := false
	if i < 0 {
		neg = true
		i = -i
	}
	var b [20]byte
	n := len(b)
	for i > 0 {
		n--
		b[n] = byte('0' + i%10)
		i /= 10
	}
	if neg {
		n--
		b[n] = '-'
	}
	return string(b[n:])
}
