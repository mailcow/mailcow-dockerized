-- Updates from version 0.2-beta

CREATE INDEX ix_session_changed ON session (changed);
CREATE INDEX ix_cache_created ON cache (created);
