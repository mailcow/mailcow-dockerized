# Workspace Boundary (Hard Rule)

Allowed paths only:
- C:\dev\repos\**
- C:\dev\worktrees\**

Never read/write outside allowed paths, including:
- C:\Users\aewoo\CascadeProjects\**
- C:\Users\aewoo\Desktop\**
- C:\Users\aewoo\Documents\**
- C:\Users\aewoo\**

Before edits, always run and report:
- Get-Location
- git rev-parse --show-toplevel
- git branch --show-current
- git status --short

If current path is outside allowed roots:
- STOP
- report boundary violation
- request explicit correction before continuing
