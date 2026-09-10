Macro Main ?
{
  Wait (event(Chapter002.Started))
  RunAsync This.MacroChapter002
}


Macro MacroChapter002 ?
{
  Action Nav_Marker01.SetCurrentBeacon()
  Action SmallHealth.SpawnSimple()
  ActionAsync Chicken.SpawnSimple()
  ActionAsync Macro_DinoControl.StartFunction("Main")
  
  RunAsync This.Fight
  //ActionAsync Portal.Activate()
  
  Wait (event(Chapter002.Finished))
  Send event ChapterControl.StartChapter003
  
} On (event(Chapter002_Trigger_Field.Activated)) do {
  Send event Chapter002.Finished
  
}



Macro Fight ?
{
  Wait (event(Chapter002.Finished))
  
} On (event(DownhillFight.Activated)) do {
  
  //spawn downhill enemies
  Action SciFi_Commander_01.SpawnSimple()
  Wait (0.10 sec)
  Action SciFi_Grunt_01.SpawnSimple()
  Wait (1.00 sec)
  Action RollingBall.SpawnSimple()
  Wait (2.00 sec)
  Action CameraOnSciFi.PlayAnimWait("Default")
  
  //SciFi_Grunt catch
} On (every (event(SciFi_Grunt_01.SpawneeAvailable))) do {
  SciFi_Grunt=SciFi_Grunt_01.GetLastSpawned()
  RunAsync SciFi_Grunt.SciFi_Grunt_Behaviour
  
  //Commander catch
} On (every (event(SciFi_Commander_01.SpawneeAvailable))) do {
  Commander_Spawnee=SciFi_Commander_01.GetLastSpawned()
  RunAsync Commander_Spawnee.Commander_Behaviour
  
  //RollingBalls catch
} On (every (event(RollingBall.SpawneeAvailable))) do {
  RollingBall_Spawnee=RollingBall.GetLastSpawned()
  RunAsync RollingBall_Spawnee.RollingBall_Behaviour
  
  
  //dropship flyby
} On (event(Mentals_Dropship_field.Activated)) do {
  Action Mentals_Dropship.SpawnSimple()
  Wait (event(Mentals_Dropship.SpawneeAvailable))
  Dropship_Spawnee=Mentals_Dropship.GetLastSpawned()
  RunAsync Dropship_Spawnee.Dropship
  
}





Macro Commander_Behaviour SLeggedCharacterEntity
{
  Wait (False)
  
  //when barbarian reaches final marker it signals other enemys to attack player
} On (event(This.LastPatrolMarkerReached)) do {
  Action This.AttackMelee("Wave")
  Action This.macSetThreatSensitivity("Standard")
}


Macro SciFi_Grunt_Behaviour SLeggedCharacterEntity
{
  Wait (event(This.LastPatrolMarkerReached))
  Action This.macSetThreatSensitivity("Standard")
}


Macro RollingBall_Behaviour CLeggedCharacterEntity
{
  Wait (event(This.LastPatrolMarkerReached))
  Action This.macSetThreatSensitivity("See through walls")
}




Macro Dropship CAircraftCharacterEntity
{
  Wait (event(This.LastPatrolMarkerReached))
  Action This.DropDead()
}
 