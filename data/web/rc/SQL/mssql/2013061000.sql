ALTER TABLE [dbo].[cache] ADD [expires] [datetime] NULL
GO
ALTER TABLE [dbo].[cache_shared] ADD [expires] [datetime] NULL
GO
ALTER TABLE [dbo].[cache_index] ADD [expires] [datetime] NULL
GO
ALTER TABLE [dbo].[cache_thread] ADD [expires] [datetime] NULL
GO
ALTER TABLE [dbo].[cache_messages] ADD [expires] [datetime] NULL
GO

UPDATE [dbo].[cache] SET [expires] = DATEADD(second, 604800, [created])
GO
UPDATE [dbo].[cache_shared] SET [expires] = DATEADD(second, 604800, [created])
GO
UPDATE [dbo].[cache_index] SET [expires] = DATEADD(second, 604800, [changed])
GO
UPDATE [dbo].[cache_thread] SET [expires] = DATEADD(second, 604800, [changed])
GO
UPDATE [dbo].[cache_messages] SET [expires] = DATEADD(second, 604800, [changed])
GO

DROP INDEX [IX_cache_created]
GO
DROP INDEX [IX_cache_shared_created]
GO
ALTER TABLE [dbo].[cache_index] DROP COLUMN [changed]
GO
ALTER TABLE [dbo].[cache_thread] DROP COLUMN [changed]
GO
ALTER TABLE [dbo].[cache_messages] DROP COLUMN [changed]
GO

CREATE INDEX [IX_cache_expires] ON [dbo].[cache]([expires]) ON [PRIMARY]
GO
CREATE INDEX [IX_cache_shared_expires] ON [dbo].[cache_shared]([expires]) ON [PRIMARY]
GO
CREATE INDEX [IX_cache_index_expires] ON [dbo].[cache_index]([expires]) ON [PRIMARY]
GO
CREATE INDEX [IX_cache_thread_expires] ON [dbo].[cache_thread]([expires]) ON [PRIMARY]
GO
CREATE INDEX [IX_cache_messages_expires] ON [dbo].[cache_messages]([expires]) ON [PRIMARY]
GO
