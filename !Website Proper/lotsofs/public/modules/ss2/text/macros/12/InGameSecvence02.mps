Macro SimbaAircraft ?
{
  global var CAircraftCharacterEntity Airplane_01
  global var CAircraftCharacterEntity Fighter_01
  
  Action SP_SimbaAircraft_01.SpawnSimple()
  ActionAsync SP_Neanderthal_03.SpawnSimple()
  ActionAsync Fighter_Start_Field.Recharge()
  Wait (event(InGameSecvence02.EndCamera02))
  
} On (event(Fighter_Start_Field.Activated)) do {
  ActionAsync SP_Fighter_01.SpawnSimple()
  
} On (event(SP_SimbaAircraft_01.SpawneeAvailable)) do {
  Airplane_01=SP_SimbaAircraft_01.GetLastSpawned()
  RunAsync Airplane_01.Airplane_01_Behavior
  
} On (event(SP_Fighter_01.SpawneeAvailable)) do {
  Fighter_01=SP_Fighter_01.GetLastSpawned()
  //ActionAsync Fighter_01.ForceFoe(Airplane_01)
  RunAsync Fighter_01.Fighter_01_Behavior
  
} On (event(Plain_Sp_Field.Activated)) do {
  Action Crates.SetSpawnSource(Airplane_01)
  ActionAsync YuHu_Sound_02.PlayOnce()
  ActionAsync CrateAmmo.SpawnSimple()
  Wait (1.00 sec)
  ActionAsync CrateHealth.SpawnSimple()
  
}


Macro CameraCheck ?
{
  Wait (event(Chapter002.Finished))
  
} On (event(Camera_02_Field.Activated)) do {
  Action Camera_02.PlayAnimWait("Default")
  ActionAsync Macro_Speech.StartFunction("SimbaPlane")
  
}



Macro Airplane_01_Behavior CAircraftCharacterEntity
{
  ActionAsync This.macSetHealth(10)
  
  Wait (event(This.Died))
  
} On (event(This.LastTacticMarkerReached)) do {
  ActionAsync This.DropDead()
  ActionAsync Explosion_Effect_01.Start()
  Wait (3.00 sec)
  ActionAsync Explosion_Effect_01.Terminate()
}




Macro Fighter_01_Behavior CAircraftCharacterEntity
{
  //Action This.PatrolMarkers()
  Wait (event(This.LastTacticMarkerReached))
  Action This.DropDead()
  Send event InGameSecvence02.EndCamera02
  
} On (3 times (event(This.TacticMarkerReached))) do {
  ActionAsync This.ForceFoe(Airplane_01)
  ActionAsync This.AttackShoot("Laser",10,TRUE)
  //ActionAsync This.macSetThreatSensitivity("Long sighted")
}
 