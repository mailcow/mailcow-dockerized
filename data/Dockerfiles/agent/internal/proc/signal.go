package proc

import (
	"os"
	"os/signal"
)

// Indirection so tests can stub these out if ever needed.
var (
	signalNotify = signal.Notify
	signalStop   = signal.Stop
)

var _ = os.Stdout // anchor import for go vet
