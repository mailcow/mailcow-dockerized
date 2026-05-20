package services

import (
	"context"
	"time"

	"github.com/mailcow/mailcow-dockerized/agent/internal/commands"
	"github.com/mailcow/mailcow-dockerized/agent/internal/proc"
)

// Services without any exec.* commands of their own — lifecycle only.
func init() {
	Register("clamd", genericBuilder("clamd", tcpProbe("127.0.0.1:3310", 2*time.Second)))
	Register("olefy", genericBuilder("olefy", tcpProbe("127.0.0.1:10055", 2*time.Second)))
	Register("postfix-tlspol", genericBuilder("postfix-tlspol", tcpProbe("127.0.0.1:8642", 2*time.Second)))
	Register("php-fpm", genericBuilder("php-fpm", tcpProbe("127.0.0.1:9001", 2*time.Second)))
	Register("acme", genericBuilder("acme", nil))
	Register("watchdog", genericBuilder("watchdog", nil))
	Register("netfilter", genericBuilder("netfilter", nil))
	Register("ofelia", genericBuilder("ofelia", nil))
	Register("dovecot-fts", genericBuilder("dovecot-fts", nil))
}

func genericBuilder(name string, probe commands.HealthProbe) Builder {
	return func(sup *proc.Supervisor) *commands.Table {
		t := commands.New(name)
		t.HealthProbe = probe
		addLifecycle(t, sup)
		return t
	}
}

func tcpProbe(addr string, timeout time.Duration) commands.HealthProbe {
	return func(ctx context.Context) error {
		return probeTCP(addr, timeout)
	}
}
