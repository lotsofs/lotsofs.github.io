for /F "tokens=*" %%A in (dir.txt) do (
echo. >> MyManual.html
echo. >> MyManual.html
echo ^<!-- ============================================================================== --^> >> MyManual.html
echo. >> MyManual.html
echo. >> MyManual.html
type "%%A" >> MyManual.html
)