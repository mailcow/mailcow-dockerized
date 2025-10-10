import subprocess
import redis
import time
import os

# Container names
SOURCE_CONTAINER = "redis-old-mailcow"
DEST_CONTAINER = "valkey-mailcow"
VALKEYPASS = os.getenv("VALKEYPASS")


def migrate_redis():
    src_redis = redis.StrictRedis(host=SOURCE_CONTAINER, port=6379, db=0, password=VALKEYPASS, decode_responses=False)
    dest_redis = redis.StrictRedis(host=DEST_CONTAINER, port=6379, db=0, password=VALKEYPASS, decode_responses=False)

    cursor = 0
    batch_size = 100
    migrated_count = 0

    print("Starting migration...")

    while True:
        cursor, keys = src_redis.scan(cursor=cursor, match="*", count=batch_size)
        keys_to_migrate = [key for key in keys if not key.startswith(b"PHPREDIS_SESSION:")]

        for key in keys_to_migrate:
            key_type = src_redis.type(key)
            print(f"Import {key} of type {key_type}")

            if key_type == b"string":
                value = src_redis.get(key)
                dest_redis.set(key, value)

            elif key_type == b"hash":
                value = src_redis.hgetall(key)
                dest_redis.hset(key, mapping=value)

            elif key_type == b"list":
                value = src_redis.lrange(key, 0, -1)
                for v in value:
                    dest_redis.rpush(key, v)

            elif key_type == b"set":
                value = src_redis.smembers(key)
                for v in value:
                    dest_redis.sadd(key, v)

            elif key_type == b"zset":
                value = src_redis.zrange(key, 0, -1, withscores=True)
                for v, score in value:
                    dest_redis.zadd(key, {v: score})

            # Preserve TTL if exists
            ttl = src_redis.ttl(key)
            if ttl > 0:
                dest_redis.expire(key, ttl)

            migrated_count += 1

        if cursor == 0:
            break  # No more keys to scan

    print(f"Migration completed! {migrated_count} keys migrated.")

    print("Forcing Valkey to save data...")
    try:
        dest_redis.save()  # Immediate RDB save (blocking)
        dest_redis.bgrewriteaof()  # Rewrites the AOF file in the background
        print("Data successfully saved to disk.")
    except Exception as e:
        print(f"Failed to save data: {e}")

# Main script execution
if __name__ == "__main__":
    try:
        migrate_redis()
    finally:
        pass
