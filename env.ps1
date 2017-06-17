$File = "mailcow.conf"

If ((Test-Path $File) -eq $True) {
    Get-Content -Path mailcow.conf | Where-Object { $_ -match '=' } |
      ForEach-Object {[Environment]::SetEnvironmentVariable($_.Split('=')[0],$_.Split('=')[1])}
  
    If ([Environment]::GetEnvironmentVariable("COMPOSE_PROJECT_NAME") -eq "mailcow-dockerized") {
      Write-Host "Job done - all enviroment variables have been set!" -foregroundcolor green
    } else {
      Write-Host "Something went wrong! " -foregroundcolor red
    }
} else {
    Write-Host "Does mailcow.conf exist?"  -foregroundcolor red
}
