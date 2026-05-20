package bus

import (
	"container/list"
	"sync"
)

// lru is a tiny request-id deduplication cache. The bus treats Pub/Sub
// retries (same request_id) as no-ops. Not a security boundary — only a
// best-effort guard against accidental double-execution.
type lru struct {
	mu   sync.Mutex
	cap  int
	idx  map[string]*list.Element
	list *list.List
}

func newLRU(cap int) *lru {
	return &lru{cap: cap, idx: make(map[string]*list.Element, cap), list: list.New()}
}

// add returns true if id is new and was inserted; false if it was already
// known (caller should skip the duplicate).
func (l *lru) add(id string) bool {
	l.mu.Lock()
	defer l.mu.Unlock()
	if _, ok := l.idx[id]; ok {
		return false
	}
	e := l.list.PushFront(id)
	l.idx[id] = e
	for l.list.Len() > l.cap {
		old := l.list.Back()
		l.list.Remove(old)
		delete(l.idx, old.Value.(string))
	}
	return true
}
