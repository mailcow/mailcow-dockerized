DROP TABLE "cache";
DROP TABLE "cache_shared";

CREATE TABLE "cache" (
    "user_id" integer NOT NULL
        REFERENCES "users" ("user_id") ON DELETE CASCADE,
    "cache_key" varchar(128) NOT NULL,
    "expires" timestamp with time zone DEFAULT NULL,
    "data" long NOT NULL,
    PRIMARY KEY ("user_id", "cache_key")
);

CREATE INDEX "cache_expires_idx" ON "cache" ("expires");


CREATE TABLE "cache_shared" (
    "cache_key" varchar(255) NOT NULL,
    "expires" timestamp with time zone DEFAULT NULL,
    "data" long NOT NULL,
    PRIMARY KEY ("cache_key")
);

CREATE INDEX "cache_shared_expires_idx" ON "cache_shared" ("expires");
