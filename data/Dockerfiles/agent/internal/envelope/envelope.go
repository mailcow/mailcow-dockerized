// Package envelope defines the wire format for the mailcow-agent control bus.
package envelope

import "time"

// Request is what the backend publishes on mailcow.control.<service>.
type Request struct {
	Cmd       string         `json:"cmd"`
	RequestID string         `json:"request_id"`
	Args      map[string]any `json:"args,omitempty"`
	ReplyTo   string         `json:"reply_to,omitempty"`
	Deadline  time.Time      `json:"deadline,omitempty"`
	IssuedBy  string         `json:"issued_by,omitempty"`
}

// Response is what the agent publishes on the reply_to channel.
type Response struct {
	RequestID  string `json:"request_id"`
	OK         bool   `json:"ok"`
	Result     any    `json:"result,omitempty"`
	Error      string `json:"error,omitempty"`
	ErrorCode  string `json:"error_code,omitempty"`
	DurationMS int64  `json:"duration_ms"`
	Node       string `json:"node,omitempty"`
}

// Error codes returned in Response.ErrorCode. Keep in sync with the V2 schema.
const (
	ErrCodeValidation         = "validation"
	ErrCodeNotFound           = "not_found"
	ErrCodeTimeout            = "timeout"
	ErrCodeUnsupportedCommand = "unsupported_command"
	ErrCodeInternal           = "internal"
)
