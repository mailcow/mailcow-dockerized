// Package stats reads cgroup CPU + memory usage and publishes them to
//
//	HASH  mailcow.stats.<service>.<node_id>
//
// with a 30s TTL. Supports both cgroup v1 and v2. The numbers are intentionally
// approximate — they replace what dockerapi exposed via /containers/<id>/stats.
package stats

import (
	"context"
	"os"
	"strconv"
	"strings"
	"time"

	"github.com/redis/go-redis/v9"
)

// Sample is one observation. CPUPercent is the share of one host CPU consumed
// since the previous sample (range 0..100*numCPU).
type Sample struct {
	CPUPercent  float64
	MemoryBytes int64
	MemoryLimit int64
	Timestamp   time.Time
}

func statsKey(service, node string) string { return "mailcow.stats." + service + "." + node }

// Publisher reads cgroup metrics and pushes them to Redis on a ticker.
type Publisher struct {
	rdb     *redis.Client
	service string
	node    string

	// previous CPU sample to derive a delta-based percent
	prevCPUNanos int64
	prevAt       time.Time
}

// NewPublisher constructs a publisher. Caller drives it via Run.
func NewPublisher(rdb *redis.Client, service, node string) *Publisher {
	return &Publisher{rdb: rdb, service: service, node: node}
}

// Run blocks on a ticker until ctx is done.
func (p *Publisher) Run(ctx context.Context, interval time.Duration) {
	t := time.NewTicker(interval)
	defer t.Stop()
	// Prime the CPU sample so the first publish has a real delta.
	if cpu, ok := readCPUNanos(); ok {
		p.prevCPUNanos = cpu
		p.prevAt = time.Now()
	}
	// Immediate first publish so the dashboard never sees a node without a
	// stats hash. CPU is 0 in this first sample (no prev delta yet); memory
	// is already accurate.
	_ = p.publish(ctx, p.sample())
	for {
		select {
		case <-ctx.Done():
			return
		case <-t.C:
			_ = p.publish(ctx, p.sample())
		}
	}
}

func (p *Publisher) sample() Sample {
	s := Sample{Timestamp: time.Now()}
	if cpu, ok := readCPUNanos(); ok {
		if !p.prevAt.IsZero() {
			dCPU := cpu - p.prevCPUNanos
			dT := s.Timestamp.Sub(p.prevAt).Nanoseconds()
			if dT > 0 && dCPU >= 0 {
				s.CPUPercent = (float64(dCPU) / float64(dT)) * 100.0
			}
		}
		p.prevCPUNanos = cpu
		p.prevAt = s.Timestamp
	}
	if mem, limit, ok := readMemory(); ok {
		s.MemoryBytes = mem
		s.MemoryLimit = limit
	}
	return s
}

func (p *Publisher) publish(ctx context.Context, s Sample) error {
	pipe := p.rdb.Pipeline()
	pipe.HSet(ctx, statsKey(p.service, p.node), map[string]any{
		"cpu_percent":  strconv.FormatFloat(s.CPUPercent, 'f', 2, 64),
		"memory_bytes": s.MemoryBytes,
		"memory_limit": s.MemoryLimit,
		"timestamp":    s.Timestamp.Unix(),
		"node_id":      p.node,
		"service":      p.service,
	})
	pipe.Expire(ctx, statsKey(p.service, p.node), 30*time.Second)
	_, err := pipe.Exec(ctx)
	return err
}

// --- cgroup readers --------------------------------------------------------

// readCPUNanos returns total CPU-nanoseconds consumed by the current cgroup,
// summed across all CPUs. Works for both cgroup v2 (cpu.stat) and v1
// (cpuacct.usage).
func readCPUNanos() (int64, bool) {
	if data, err := os.ReadFile("/sys/fs/cgroup/cpu.stat"); err == nil {
		// v2: lines like "usage_usec 12345"
		for _, line := range strings.Split(string(data), "\n") {
			if strings.HasPrefix(line, "usage_usec ") {
				n, err := strconv.ParseInt(strings.TrimPrefix(line, "usage_usec "), 10, 64)
				if err == nil {
					return n * 1000, true // µs → ns
				}
			}
		}
	}
	if data, err := os.ReadFile("/sys/fs/cgroup/cpuacct/cpuacct.usage"); err == nil {
		n, err := strconv.ParseInt(strings.TrimSpace(string(data)), 10, 64)
		if err == nil {
			return n, true
		}
	}
	return 0, false
}

// readMemory returns current usage and limit in bytes.
func readMemory() (int64, int64, bool) {
	// v2
	if cur, err := readInt("/sys/fs/cgroup/memory.current"); err == nil {
		limit, _ := readInt("/sys/fs/cgroup/memory.max")
		return cur, limit, true
	}
	// v1
	if cur, err := readInt("/sys/fs/cgroup/memory/memory.usage_in_bytes"); err == nil {
		limit, _ := readInt("/sys/fs/cgroup/memory/memory.limit_in_bytes")
		return cur, limit, true
	}
	return 0, 0, false
}

func readInt(path string) (int64, error) {
	b, err := os.ReadFile(path)
	if err != nil {
		return 0, err
	}
	s := strings.TrimSpace(string(b))
	if s == "max" {
		return -1, nil
	}
	return strconv.ParseInt(s, 10, 64)
}
