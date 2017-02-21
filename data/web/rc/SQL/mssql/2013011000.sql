-- Upgrades from 0.9-beta

CREATE TABLE [dbo].[system] (
    [name] [varchar] (64) COLLATE Latin1_General_CI_AI NOT NULL ,
    [value] [text] COLLATE Latin1_General_CI_AI 
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO

ALTER TABLE [dbo].[system] WITH NOCHECK ADD
    CONSTRAINT [PK_system_name] PRIMARY KEY CLUSTERED
    (
        [name]
    ) ON [PRIMARY]
GO
