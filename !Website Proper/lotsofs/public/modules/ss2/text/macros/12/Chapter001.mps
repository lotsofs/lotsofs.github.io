Macro Chapter001 ?
{
  Wait (event(Chapter001.Started))
  Run This.Fight
}

Macro Fight ?
{
  ActionAsync SP_Neanderthal_01.SpawnSimple()
  ActionAsync SP_Shells_06.SpawnGroup()
  Wait (event(This.EndChapter001))
  
} On (event(Detector_Chapter002.Activated)) do {
  Send event ChapterControl.StartChapter002
  Send event This.EndChapter001
  
} On (event(DetectorFighter.Activated)) do {
  Fighter02=Fighter_02.SpawnOne()
  RunAsync Fighter02.FighterOneRound
  Wait (5.00 sec)
  Fighter01=Fighter_01.SpawnOne()
  RunAsync Fighter01.FighterOneRound
}


Macro FighterOneRound CAircraftCharacterEntity
{
  Wait (False)
  
} On (5 times (event(This.TacticMarkerReached))) do {
  ActionAsync This.Disappear()
  
}

 