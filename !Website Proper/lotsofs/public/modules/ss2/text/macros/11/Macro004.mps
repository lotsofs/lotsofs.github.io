Macro Main ?
{
  Wait (event(Chapter004.Started))
  
  ActionAsync Camera_OnBarbarian.PlayAnimWait("Default")
  Wait (1.75 sec)
  Action Mouth_Enterance.macDestroyOnePhase()
  Wait (0.10 sec)
  ActionAsync Barbarian_Smasher.SpawnSimple()
  Wait (4.00 sec)
  ActionAsync Lizard_01.SpawnSimple()
  
  Wait (event(Chapter004.Finished))
  
  
  
} On (event(EndDetector.Activated)) do {
  //end of level - load next
  Send event ChapterControl.StartNextLevelLink
  
  
} On (event(Barbarian_Smasher.SpawneeAvailable)) do {
  Barbarian=Barbarian_Smasher.GetLastSpawned()
  RunAsync Barbarian.BarbarianAttack
}



Macro BarbarianAttack CLeggedCharacterEntity
{
  ActionAsync This.macSetHealth(600)
  //ActionAsync This.PlaySchemeSound("Sight")
  ActionAsync This.PlayAnimWait("Threat")
  Wait (1.00 sec)
  ActionAsync This.PlaySchemeSound("Sight")
  Wait (2.00 sec)
  ActionAsync Macro_Speech.StartFunction("Barbarian")
  Action This.SetTacticEntity(Tactic_OrderedMoving)
  Wait (False)
  
} On (1 times (event(This.LastTacticMarkerReached))) do {
  Action This.macSetThreatSensitivity("Long sighted")
  
  
} On (event(Barbarian_Smasher.AllKilled)) do {
  ActionAsync Macro_Speech.StartFunction("BarbarianDied")
  
}

 