-- Updates from version 0.7-beta

ALTER TABLE [dbo].[session] ALTER COLUMN [sess_id] [varchar] (128) COLLATE Latin1_General_CI_AI NOT NULL
GO
