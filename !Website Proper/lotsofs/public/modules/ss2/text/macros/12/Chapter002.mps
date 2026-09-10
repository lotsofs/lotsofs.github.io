Macro Chapter002 ?
{
  Wait (event(Chapter02.Started))
  RunAsync This.Fight
  RunAsync This.ChapterControl
  Wait (event(Chapter002.Finished))
}


Macro ChapterControl ?
{
  Wait (event(Chapter002.Finished))
  
} On (event(End_Chapter002_Field.Activated)) do {
  Send event ChapterControl.StartChapter003
  Send event This.EndChapter002
  
}



Macro Fight ?
{
  ActionAsync Fight_02_Field.Recharge()
  ActionAsync Fight_04_Field.Recharge()
  ActionAsync Fight_05_Field.Recharge()
  ActionAsync End_Chapter002_Field.Recharge()
  Wait (event(This.EndChapter002))
  
} On (event(Fight_02_Field.Activated)) do {
  ActionAsync SP_Neanferthal_02.SpawnSimple()
  ActionAsync SP_Barbarian_01.SpawnSimple()
  ActionAsync SP_Shells_07.SpawnGroup()
  
} On (event all(BarbarianAndNeanderthals.AllKilled)) do {
  Action Camera_02_Field.Recharge()
  Action Macro_SimbaPlane.StartFunction("CameraCheck")
  
} On (every (event(SP_Barbarian_01.SpawneeAvailable))) do {
  Barbarian01=SP_Barbarian_01.GetLastSpawned()
  RunAsync Barbarian01.Barbarian_01_Behavior
  
} On (event(Fight_04_Field.Activated)) do {
  RunAsync This.GorillaLizards
  
} On (event(Fight_05_Field.Activated)) do {
  ActionAsync Lizard_Catapult.SpawnSimple()
  
} On (every (event(SP_Neanferthal_02.SpawneeAvailable))) do {
  Neanderthal02=SP_Neanferthal_02.GetLastSpawned()
  RunAsync Neanderthal02.Neanferthal_02_Behavior
}



Macro Neanferthal_02_Behavior SLeggedCharacterEntity
{
  ActionAsync This.PatrolMarkers()
  Wait (event(SP_Neanferthal_02.AllKilled))
  
} On (every (event(This.LastPatrolMarkerReached))) do {
  ActionAsync This.macSetThreatSensitivity("Long sighted")
}


Macro Barbarian_01_Behavior CLeggedCharacterEntity
{
  ActionAsync This.PatrolMarkers()
  Wait (event(SP_Barbarian_01.AllKilled))
  
} On (every (event(This.LastPatrolMarkerReached))) do {
  ActionAsync This.macSetThreatSensitivity("Long sighted")
}


Macro GorillaLizards CLeggedCharacterEntity
{
  ActionAsync GorzillaLizard.SpawnMaintainGroup()
  Wait (3.00 sec)
  ActionAsync SP_Neanderthal_07.SpawnSimple()
  Wait (event(GorzillaEnemies.AllKilled))
  
} On (every (event(SP_Neanderthal_07.SpawneeAvailable))) do {
  Neanderthal07=SP_Neanderthal_07.GetLastSpawned()
  RunAsync Neanderthal07.NeanderthalWithLizards
}

Macro NeanderthalWithLizards CLeggedCharacterEntity
{
  Wait (event(SP_Neanderthal_07.AllKilled))
  
} On (every (event(This.LastPatrolMarkerReached))) do {
  ActionAsync This.macSetThreatSensitivity("Long sighted")
}


 