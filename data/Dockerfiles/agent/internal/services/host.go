package services

import (
	"bufio"
	"context"
	"fmt"
	"os"
	"strconv"
	"strings"
	"syscall"
	"time"

	"github.com/mailcow/mailcow-dockerized/agent/internal/commands"
	"github.com/mailcow/mailcow-dockerized/agent/internal/proc"
)

func init() { Register("host", buildHost) }

// hostProcRoot is where the host-agent container mounts /proc. If we're not
// running as host-agent, falling back to /proc still produces sensible numbers
// (the container's own view) so dashboards don't blank out in unit tests.
var hostProcRoot = "/host/proc"

func resolveProc(p string) string {
	if _, err := os.Stat(hostProcRoot); err == nil {
		return hostProcRoot + p
	}
	return "/proc" + p
}

func buildHost(_ *proc.Supervisor) *commands.Table {
	t := commands.New("host")
	// No lifecycle — the host-agent container has no main process to manage.

	t.Register("exec.df", func(ctx context.Context, args map[string]any) (any, error) {
		path := commands.ArgStringOpt(args, "path", "/")
		var stat syscall.Statfs_t
		if err := syscall.Statfs(path, &stat); err != nil {
			return nil, fmt.Errorf("statfs %s: %w", path, err)
		}
		size := int64(stat.Blocks) * int64(stat.Bsize)
		free := int64(stat.Bavail) * int64(stat.Bsize)
		used := size - free
		return map[string]any{
			"path":      path,
			"size":      size,
			"used":      used,
			"available": free,
		}, nil
	})

	t.Register("exec.host-stats", func(ctx context.Context, _ map[string]any) (any, error) {
		return readHostStats()
	})

	return t
}

func readHostStats() (map[string]any, error) {
	out := map[string]any{
		"system_time":  time.Now().Format("2006-01-02 15:04:05"),
		"architecture": readArchitecture(),
	}

	if uptime, err := readUptime(); err == nil {
		out["uptime"] = int64(uptime)
	} else {
		out["uptime"] = int64(0)
	}

	cores := readCPUCores()
	cpuUsage, _ := sampleHostCPU(500 * time.Millisecond)
	out["cpu"] = map[string]any{
		"cores": cores,
		"usage": cpuUsage,
	}

	memTotal, memUsage := readMemoryTotalAndUsagePct()
	out["memory"] = map[string]any{
		"total": memTotal, // bytes
		"usage": memUsage, // percent 0..100
	}

	return out, nil
}

// readArchitecture returns the host's machine architecture (e.g. "x86_64",
// "aarch64"). Falls back to a single dash if syscall.Uname fails.
func readArchitecture() string {
	var u syscall.Utsname
	if err := syscall.Uname(&u); err != nil {
		return "-"
	}
	return charsToString(u.Machine[:])
}

func charsToString(b []int8) string {
	out := make([]byte, 0, len(b))
	for _, c := range b {
		if c == 0 {
			break
		}
		out = append(out, byte(c))
	}
	return string(out)
}

// readCPUCores counts `^processor` lines in /proc/cpuinfo. On a container
// with /host/proc bind-mounted this gives the host's logical CPU count,
// not the container's cgroup limits.
func readCPUCores() int {
	f, err := os.Open(resolveProc("/cpuinfo"))
	if err != nil {
		return 0
	}
	defer f.Close()
	n := 0
	sc := bufio.NewScanner(f)
	for sc.Scan() {
		if strings.HasPrefix(sc.Text(), "processor") {
			n++
		}
	}
	return n
}

// readMemoryTotalAndUsagePct reads /proc/meminfo and returns (total_bytes,
// usage_pct_0_100). "Usage" is computed as (Total - Available)/Total which
// matches what tools like `free` show as "used".
func readMemoryTotalAndUsagePct() (int64, int) {
	f, err := os.Open(resolveProc("/meminfo"))
	if err != nil {
		return 0, 0
	}
	defer f.Close()

	var total, available int64
	sc := bufio.NewScanner(f)
	for sc.Scan() {
		fields := strings.Fields(sc.Text())
		if len(fields) < 2 {
			continue
		}
		switch fields[0] {
		case "MemTotal:":
			total = parseInt64(fields[1]) * 1024
		case "MemAvailable:":
			available = parseInt64(fields[1]) * 1024
		}
	}
	if total <= 0 {
		return 0, 0
	}
	used := total - available
	if available <= 0 {
		used = total
	}
	pct := int(float64(used) / float64(total) * 100.0)
	if pct < 0 {
		pct = 0
	}
	if pct > 100 {
		pct = 100
	}
	return total, pct
}

func readUptime() (float64, error) {
	b, err := os.ReadFile(resolveProc("/uptime"))
	if err != nil {
		return 0, err
	}
	fields := strings.Fields(string(b))
	if len(fields) < 1 {
		return 0, fmt.Errorf("malformed uptime")
	}
	return strconv.ParseFloat(fields[0], 64)
}

// sampleHostCPU returns CPU utilization (0..100) sampled over `window`.
func sampleHostCPU(window time.Duration) (float64, error) {
	a, err := readCPULine()
	if err != nil {
		return 0, err
	}
	time.Sleep(window)
	b, err := readCPULine()
	if err != nil {
		return 0, err
	}
	totalA, totalB := sum(a), sum(b)
	idleA, idleB := a[3], b[3]
	dTotal, dIdle := totalB-totalA, idleB-idleA
	if dTotal == 0 {
		return 0, nil
	}
	return 100.0 * float64(dTotal-dIdle) / float64(dTotal), nil
}

func readCPULine() ([]int64, error) {
	f, err := os.Open(resolveProc("/stat"))
	if err != nil {
		return nil, err
	}
	defer f.Close()
	sc := bufio.NewScanner(f)
	if !sc.Scan() {
		return nil, fmt.Errorf("empty /proc/stat")
	}
	fields := strings.Fields(sc.Text())
	if len(fields) < 5 || fields[0] != "cpu" {
		return nil, fmt.Errorf("unexpected /proc/stat first line")
	}
	out := make([]int64, 0, len(fields)-1)
	for _, f := range fields[1:] {
		n, err := strconv.ParseInt(f, 10, 64)
		if err != nil {
			return nil, err
		}
		out = append(out, n)
	}
	return out, nil
}

func sum(xs []int64) int64 {
	var s int64
	for _, x := range xs {
		s += x
	}
	return s
}

func parseInt64(s string) int64 {
	n, _ := strconv.ParseInt(s, 10, 64)
	return n
}
