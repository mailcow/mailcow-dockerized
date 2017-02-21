ALTER TABLE "cache" ADD expires timestamp with time zone DEFAULT NULL;
ALTER TABLE "cache_shared" ADD expires timestamp with time zone DEFAULT NULL;
ALTER TABLE "cache_index" ADD expires timestamp with time zone DEFAULT NULL;
ALTER TABLE "cache_thread" ADD expires timestamp with time zone DEFAULT NULL;
ALTER TABLE "cache_messages" ADD expires timestamp with time zone DEFAULT NULL;

-- initialize expires column with created/changed date + 7days
UPDATE "cache" SET expires = created + interval '604800 seconds';
UPDATE "cache_shared" SET expires = created + interval '604800 seconds';
UPDATE "cache_index" SET expires = changed + interval '604800 seconds';
UPDATE "cache_thread" SET expires = changed + interval '604800 seconds';
UPDATE "cache_messages" SET expires = changed + interval '604800 seconds';

DROP INDEX cache_created_idx;
DROP INDEX cache_shared_created_idx;
ALTER TABLE "cache_index" DROP "changed";
ALTER TABLE "cache_thread" DROP "changed";
ALTER TABLE "cache_messages" DROP "changed";

CREATE INDEX cache_expires_idx ON "cache" (expires);
CREATE INDEX cache_shared_expires_idx ON "cache_shared" (expires);
CREATE INDEX cache_index_expires_idx ON "cache_index" (expires);
CREATE INDEX cache_thread_expires_idx ON "cache_thread" (expires);
CREATE INDEX cache_messages_expires_idx ON "cache_messages" (expires);
