DROP TABLE [dbo].[cache]
GO
DROP TABLE [dbo].[cache_shared]
GO

CREATE TABLE [dbo].[cache] (
  [user_id] [int] NOT NULL ,
  [cache_key] [varchar] (128) COLLATE Latin1_General_CI_AI NOT NULL ,
  [expires] [datetime] NULL ,
  [data] [text] COLLATE Latin1_General_CI_AI NOT NULL 
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
CREATE TABLE [dbo].[cache_shared] (
  [cache_key] [varchar] (255) COLLATE Latin1_General_CI_AI NOT NULL ,
  [expires] [datetime] NULL ,
  [data] [text] COLLATE Latin1_General_CI_AI NOT NULL
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
ALTER TABLE [dbo].[cache] ADD
  CONSTRAINT [DF_cache_user_id] DEFAULT ('0') FOR [user_id],
  CONSTRAINT [DF_cache_cache_key] DEFAULT ('') FOR [cache_key],
GO
CREATE INDEX [IX_cache_expires] ON [dbo].[cache]([expires]) ON [PRIMARY]
GO
CREATE INDEX [IX_cache_shared_expires] ON [dbo].[cache_shared]([expires]) ON [PRIMARY]
GO
ALTER TABLE [dbo].[cache] WITH NOCHECK ADD
  PRIMARY KEY CLUSTERED (
    [user_id],[cache_key]
  ) ON [PRIMARY]
GO
ALTER TABLE [dbo].[cache_shared] WITH NOCHECK ADD
  PRIMARY KEY CLUSTERED (
    [cache_key]
  ) ON [PRIMARY]
GO
