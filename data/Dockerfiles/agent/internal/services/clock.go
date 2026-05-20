package services

import "time"

// nowStamp returns a sortable timestamp used to suffix moved/garbage maildirs
// so repeated cleanups don't collide.
func nowStamp() string {
	return time.Now().UTC().Format("20060102T150405Z")
}
