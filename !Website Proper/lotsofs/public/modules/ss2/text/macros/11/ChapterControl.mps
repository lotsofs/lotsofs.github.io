Macro ChapterControl ?
{
  Wait (False)
  
  //} On (event(ChapterControl.StartChapter001)) do {
  //Action Chapter001.Start()
  
} On (event(ChapterControl.StartChapter002)) do {
  Action Chapter002.Start()
  
} On (event(ChapterControl.StartChapter003)) do {
  Action Chapter003.Start()
  
} On (event(ChapterControl.StartChapter004)) do {
  Action Chapter004.Start()
  
} On (event(ChapterControl.StartNextLevelLink)) do {
  Action Nextlevellink.Start()
  
}
 