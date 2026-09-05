Macro First_Fight ?
{
  Wait (event(Chapter001.Started))
  Run This.Fight
  Wait (event(Chapter001.First_Main_Attack_Finished))
}

Macro Fight ?
{
  global var CLeggedCharacterEntity Spawnee
  Action Barrier_Mover.PlayAnim("Default")
  Action Chicken.SpawnSimple()
  Action Simba_Civil_01.SpawnSimple()
  Action Simba_Civil_02.SpawnSimple()
  Action Simba_Warriors.SpawnSimple()
  Action Simba_Chief.SpawnSimple()
  Action Child.SpawnSimple()
  Action Health_02.SpawnSimple()
  
  //Part is missing, there should fly flaming arrows at village
  Wait (5.00 sec)
  
  //Some houses are on fire  
  Wait (3.00 sec)
  
  //Civilians are runing form burning buildings
  ActionAsync Simba_From_Burning_House.SpawnSimple()
  Wait (2.00 sec)
  
  //First enemys jumps over the village fence
  ActionAsync Neanderthal_01.SpawnGroup()
  Wait (False)
  
} On (event(Neanderthal_01.OneKilled)) do {
  Action Neanderthal_04.SpawnMaintainGroup()
  Action Lizard_01.SpawnMaintainGroup()
  
} On (event(Neanderthal_04.OneKilled)) do {
  Action Neanderthal_01_.SpawnGroup()
  ActionAsync Neanderthal_01.SpawnGroup()
  
} On (every (2 times (event(Neanderthal_04.OneKilled)))) do {
  ActionAsync Neanderthal_12.SpawnGroup()
  
  
} On (event all(First_Enemy_Group.AllKilled)) do {
  //Door crushing team appears
  Action Neanderthal_02.SpawnSimple()
  Action Neanderthal_03.SpawnSimple()
  Send event This.Camera_01_Start
  Wait (event(Neanderthal_02.SpawneeAvailable))
  DoorSmasher=Neanderthal_02.GetLastSpawned()
  RunAsync DoorSmasher.DoorSmasher
  Wait (event(Neanderthal_03.SpawneeAvailable))
  DoorPusher=Neanderthal_03.GetLastSpawned()
  RunAsync DoorPusher.DoorPusher
  
  
  //Now camera shows Neanderthals raming doors 
} On (event(This.Camera_01_Start)) do {
  Wait (3.00 sec)
  ActionAsync Camera_01.PlayAnimWait("Default")
  Wait (5.00 sec)
  Send event Chapter001.First_Wave_Finished
  Wait (15.00 sec)
  
  //Second enemy attack on village
  Action Neanderthal_05.SpawnSimple()
  Action Lizard_02.SpawnMaintainGroup()
  Action Neanderthal_06.SpawnSimple()
  
} On (event(Second_Enemy_Group.AllKilled)) do {
  Wait (4.00 sec)
  Action Neanderthals_7_9_10.SpawnMaintainGroup()
  
  
  //Albino is appearing and smashes the door  
} On (21 times (event(Third_Enemy_Group.OneKilled))) do {
  Wait (20.00 sec)
  Send event Chapter001.Second_Wave_Finished
  Wait (20.00 sec)
  Send event Chapter001.First_Enemies_Group_Killed
  
} On (event all(Third_Enemy_Group.AllKilled)) do {
  Send event Chapter001.Second_Wave_Finished
  Wait (15.00 sec)
  Send event Chapter001.First_Enemies_Group_Killed
  
} On (every (event(Simba_Chief.SpawneeAvailable))) do {
  Spawnee=Simba_Chief.GetLastSpawned()
  Action Spawnee.BeInvulnerable(TRUE)
  Wait (event(Chapter001.Second_Wave_Finished))
  
  
} On (every (event(Simba_Warriors.SpawneeAvailable))) do {
  Spawnee=Simba_Warriors.GetLastSpawned()
  Action Spawnee.BeInvulnerable(TRUE)
  
} On (event(Simba_Civil_01.SpawneeAvailable)) do {
  Spawnee=Simba_Civil_01.GetLastSpawned()
  Wait (event(Chapter001.First_Wave_Finished))
  If (Spawnee!=NULL) {
    RunAsync Spawnee.Shells
  }
  Wait (event(Chapter001.Second_Wave_Finished))
  If (Spawnee!=NULL) {
    RunAsync Spawnee.Shells
  }
  
} On (event(Simba_Civil_02.SpawneeAvailable)) do {
  Spawnee=Simba_Civil_02.GetLastSpawned()
  Wait (event(Chapter001.First_Wave_Finished))
  If (Spawnee!=NULL) {
    RunAsync Spawnee.Shells
  }
  Wait (event(Chapter001.Second_Wave_Finished))
  If (Spawnee!=NULL) {
    RunAsync Spawnee.Shells
  }
} On (event(Simba_From_Burning_House.SpawneeAvailable)) do {
  Spawnee=Simba_From_Burning_House.GetLastSpawned()
  Wait (event(Chapter001.First_Wave_Finished))
  If (Spawnee!=NULL) {
    Run Spawnee.Health
  }
  Wait (event(Chapter001.Second_Wave_Finished))
  If (Spawnee!=NULL) {
    RunAsync Spawnee.Shells
  }
  
} On (event(Chapter001.First_Enemies_Group_Killed)) do {
  RunAsync This.AlbinoDoorDestruction
  Send event Chapter001.First_Main_Attack_Finished
}






Macro DoorSmasher CLeggedCharacterEntity
{
  global var INDEX Value
  Value=0
  ActionAsync This.BeInvulnerable(TRUE)
  Wait (event(This.LastPatrolMarkerReached))
  Send event Chapter001.First_Primitive_Start_Smash
  ActionAsync Doors.PlayAnimLoop("Slam")
  ActionAsync Doors_craching_Sound.PlayLooping()
  Wait (event(Chapter001.Camera_02_Start))
  ActionAsync Doors.StopAnim()
  ActionAsync Doors_craching_Sound.StopLooping()
  Value=1
  //Albino is coming from back
  Wait (1.00 sec)
  Action This.macSetLookTarget(Look_Marker)
  Wait (1.40 sec)
  ActionAsync This.StopAnim()
  Action This.SetPatrolingSpeedMultiplier(1)
  Action This.PatrolMarkers(Marker_01)
  Wait (event(This.finished))
  
} On (2 times (event(This.LastPatrolMarkerReached))) do {
  Action This.macSetLookTarget(NULL)
  ActionAsync This.BeInvulnerable(FALSE)
  Wait (6.00 sec)
  If (This!=NULL) {
    Action This.macSetThreatSensitivity("Standard")
    Send event This.finished
  }
  
} On (every (event(Chapter001.First_Primitive_Start_Smash))) do {
  If (Value==0) {
    Send event Chapter001.Right_Club_Smash_Sound
    Action This.AttackMelee("Melee")
    Send event Chapter001.First_Primitive_Start_Smash
  }
} On (every (event(Chapter001.Right_Club_Smash_Sound))) do {
  Wait (0.50 sec)
  ActionAsync Rught_Club_Smash_Sound.PlayOnce()
}


Macro DoorPusher CLeggedCharacterEntity
{
  global var INDEX Value
  Value=0
  ActionAsync This.BeInvulnerable(TRUE)
  Wait (event(This.LastPatrolMarkerReached))
  Send event Chapter001.First_Primitive_Start_Push
  Wait (event(Chapter001.Camera_02_Start))
  Value=1
  //Albino is coming from back
  Wait (1.00 sec)
  Action Doors.StopAllAnims()
  Action This.macSetLookTarget(Look_Marker)
  Wait (2.00 sec)
  ActionAsync This.StopAnim()
  Action This.SetPatrolingSpeedMultiplier(1)
  Action This.PatrolMarkers(Marker_02)
  Wait (event(This.finished))
  
} On (2 times (event(This.LastPatrolMarkerReached))) do {
  Action This.macSetLookTarget(NULL)
  ActionAsync This.BeInvulnerable(FALSE)
  Wait (6.00 sec)
  If (This!=NULL) {
    Action This.macSetThreatSensitivity("Standard")
    Send event This.finished
  }
  
} On (every (event(Chapter001.First_Primitive_Start_Push))) do {
  If (Value==0) {
    Send event Chapter001.Left_Club_Smash_Sound
    Action This.AttackMelee("Melee")
    Send event Chapter001.First_Primitive_Start_Push
  }
} On (every (event(Chapter001.Left_Club_Smash_Sound))) do {
  Wait (0.50 sec)
  ActionAsync Left_Club_Smash_Sound.PlayOnce()
}



Macro AlbinoDoorDestruction ?
{
  Wait (2.00 sec)
  ActionAsync Camera_02.PlayAnimWait("Default")
  Wait (1.00 sec)
  Albino_spawnee=Albino.SpawnOne()
  RunAsync Albino_spawnee.Albino
  Action Albino_spawnee.BeInvulnerable(TRUE)
  Send event Chapter001.Camera_02_Start
  Wait (4.50 sec)
  //ActionAsync Camera_03.PlayAnimWait("Default")
  Send event Chapter001.Camera_03_Start
}

Macro Albino CLeggedCharacterEntity
{
  ActionAsync This.macSetHealth(3000)
  //Wait (event(Chapter001.Camera_03_Start))
  Action This.PatrolMarkers(marker)
  Wait (event(This.finished))
  
} On (1 times (event(This.MarkerReached))) do {
  Action This.StopPatrolingMarkers()
  Wait (0.60 sec)
  Action Mask.Disappear()
  Action Doors.macDestroyOnePhase()
  Send event Chapter001.Doors_destroyed
  Wait (1.00 sec)
  ActionAsync Camera001.PlayAnimWait("Default")
  Wait (2.00 sec)
  ActionAsync Camera001.Stop()
  Action This.macSetThreatSensitivity("Damage and sense")
  Action This.BeInvulnerable(FALSE)
  Wait (0.01 sec)
  Action This.PatrolMarkers(marker_02)
  
  
  
} On (1 times (event(This.MarkerReached))) do {
  Wait (0.10 sec)
  Action This.AttackMelee("Slap_LeftHand")
  
  
  
  
} On (event(This.LastPatrolMarkerReached)) do {
  If (This!=NULL) {
    Action This.macSetThreatSensitivity("Standard")
    Send event This.finished
  }
}

Macro Shells ?
{
  Shells=Shells_Ammo.SpawnOne()
  Wait (1.00 sec)
  Action This.macPickTool(Shells,TRUE)
}

Macro Health ?
{
  Health_S=Health_Item.SpawnOne()
  Wait (1.00 sec)
  Action This.macPickTool(Health_S,TRUE)
}






 