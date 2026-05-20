// Package commands defines the per-service handler table. The bus dispatcher
// looks up handlers by name and wraps the result in an envelope.Response.
package commands

import (
	"context"
	"errors"
)

// ErrNotFound signals that the target (queue id, mailbox, …) doesn't live on
// this node. For broadcast operations the aggregator still counts success if
// any other node returns ok.
var ErrNotFound = errors.New("not_found")

// ErrValidation indicates a missing or malformed argument.
var ErrValidation = errors.New("validation")

// Handler executes a single command for a service.
type Handler func(ctx context.Context, args map[string]any) (any, error)

// HealthProbe returns nil if the supervised service is healthy, error otherwise.
// Shared between the `healthcheck` sub-command and the agent's heartbeat loop.
type HealthProbe func(ctx context.Context) error

// Table is the per-service command registry built once at startup.
type Table struct {
	Service     string
	Handlers    map[string]Handler
	HealthProbe HealthProbe
}

// New constructs an empty table for a service.
func New(service string) *Table {
	return &Table{Service: service, Handlers: make(map[string]Handler)}
}

// Register adds a handler. Duplicate registration panics — wiring bugs should
// be loud.
func (t *Table) Register(cmd string, h Handler) {
	if _, dup := t.Handlers[cmd]; dup {
		panic("commands: duplicate handler " + t.Service + "/" + cmd)
	}
	t.Handlers[cmd] = h
}

// Lookup returns the handler for cmd or nil.
func (t *Table) Lookup(cmd string) Handler {
	return t.Handlers[cmd]
}

// ArgString extracts a required string argument.
func ArgString(args map[string]any, key string) (string, error) {
	v, ok := args[key]
	if !ok {
		return "", errArg(key, "missing")
	}
	s, ok := v.(string)
	if !ok || s == "" {
		return "", errArg(key, "must be non-empty string")
	}
	return s, nil
}

// ArgStringOpt returns an optional string argument with a default.
func ArgStringOpt(args map[string]any, key, def string) string {
	if v, ok := args[key]; ok {
		if s, ok := v.(string); ok && s != "" {
			return s
		}
	}
	return def
}

func errArg(key, reason string) error {
	return &validationError{key: key, reason: reason}
}

type validationError struct{ key, reason string }

func (e *validationError) Error() string { return "arg " + e.key + ": " + e.reason }
func (e *validationError) Is(target error) bool {
	return target == ErrValidation
}
