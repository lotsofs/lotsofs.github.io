Macro Second_Fight ?
{
  Wait (event(Chapter001.First_Main_Attack_Finished))
  Run This.Fight
  Wait (event(ChapterControlMacro.Chapter001_Finished))
  
}
Macro Fight ?
{
  global var INDEX DoorsDestroyed
  DoorsDestroyed=0
  Action Neanderthal_13.SpawnSimple()
  Action Barbarian_01.SpawnSimple()
  Wait (event(ChapterControlMacro.Chapter001_Finished))
  
  
} On (event(Barbarian_01.OneKilled)) do {
  Action Neanderthal_14.SpawnMaintainGroup()
} On (event(Neanderthal_13.AllKilled)) do {
  Action Barbarian_02.SpawnMaintainGroup()
  Action Neanderthal_15.SpawnMaintainGroup()
  Wait (4.00 sec)
  Action Neanderthal_16.SpawnMaintainGroup()
  Wait (4.00 sec)
  Action Neanderthal_17.SpawnMaintainGroup()
  
} On (every (event(Neanderthal_13.SpawneeAvailable))) do {
  Neanderthal_Spawnee=Neanderthal_13.GetLastSpawned()
  If (DoorsDestroyed==1) {
    RunAsync Neanderthal_Spawnee.AfterDoors_Behaviour
  } else {
    RunAsync Neanderthal_Spawnee.BeforeDoors_Behaviour
  }
} On (every (event(Barbarian_01.SpawneeAvailable))) do {
  Barbarian_Spawnee=Barbarian_01.GetLastSpawned()
  If (DoorsDestroyed==1) {
    RunAsync Barbarian_Spawnee.AfterDoors_Behaviour
  } else {
    RunAsync Barbarian_Spawnee.BeforeDoors_Behaviour
  }
} On (16 times (event(Enemys_Killed.OneKilled))) do {
  Wait (120.00 sec)
  Send event ChapterControlMacro.Chapter001_Finished
  
} On (every (event all(Enemys_Killed.AllKilled))) do {
  Wait (5.00 sec)
  Send event ChapterControlMacro.Chapter001_Finished
  
} On (event(Chapter001.Doors_destroyed)) do {
  DoorsDestroyed=1
}

Macro BeforeDoors_Behaviour SLeggedCharacterEntity
{
  Wait (event(Chapter001.Doors_destroyed))
  If (This!=NULL) {
    Action This.macSetThreatSensitivity("Damage and sense")
    Wait (3.00 sec)
    If (This!=NULL) {
      Action This.PatrolMarkers()
    }
  }
} On (event(This.LastPatrolMarkerReached)) do {
  Action This.macSetThreatSensitivity("Standard")
}

Macro AfterDoors_Behaviour SLeggedCharacterEntity
{
  If (This!=NULL) {
    Action This.macSetThreatSensitivity("Damage and sense")
    Wait (3.00 sec)
    If (This!=NULL) {
      Action This.PatrolMarkers()
    }
  }
} On (event(This.LastPatrolMarkerReached)) do {
  Action This.macSetThreatSensitivity("Standard")
}
 