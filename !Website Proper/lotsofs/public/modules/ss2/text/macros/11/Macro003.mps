Macro Main ?
{
  Wait (event(Chapter003.Started))
  
  RunAsync This.Dropships
  Wait (8.00 sec)
  ActionAsync WorldInfo.ForceMusic("Tense")
  
  Wait (event(Chapter003.Finished))
  
  //start chapter004
  Wait (4.00 sec)
  Send event ChapterControl.StartChapter004
  
} On (event(Chapter003.DropshipOnPosition)) do {
  RunAsync This.Fight
  
} On (event(Chapter003.DropshipOnPosition_02)) do {
  RunAsync This.Fight02
  
}




Macro Dropships CAircraftCharacterEntity
{
  global var CAircraftCharacterEntity Dropship
  global var CAircraftCharacterEntity Dropship02
  
  //left dropship start
  Dropship=Dropship_Spawner.SpawnOne()
  ActionAsync Dropship.BeInvulnerable(TRUE)
  Action SciFi_Commander.SetSpawnSource(Dropship)
  Action SciFi_Multispawner.SetSpawnSource(Dropship)
  ActionAsync Macro_DropshipControl.StartFunction("DropshipControl",Dropship)
  
  //right dropship start
  Dropship02=Dropship_Spawner_02.SpawnOne()
  ActionAsync Dropship02.BeInvulnerable(TRUE)
  //all three SciFis are in multispawner
  Action SciFi_Multispawner02.SetSpawnSource(Dropship02)
  ActionAsync Macro_DropshipControl_02.StartFunction("DropshipControl",Dropship02)
  
}


Macro Fight02 CLeggedCharacterEntity
{
  //spawning SciFi soldiers
  ActionAsync SciFi_Multispawner02.SpawnSimple()
  Wait (event(Chapter003.Finished))
  
} On (event(SciFi_Multispawner02.AllSpawned)) do {
  Send event Chapter003.AllSpawned_02
  
}



Macro Fight CLeggedCharacterEntity
{
  //spawning SciFi soldiers out of dropship
  Action SciFi_Commander.SpawnSimple()
  Wait (1.00 sec)
  Action SciFi_Multispawner.SpawnSimple()
  
  Wait (event(Chapter003.Finished))
  
  
} On (event(SciFi_Commander.SpawneeAvailable)) do {
  Commander_Spawnee=SciFi_Commander.GetLastSpawned()
  RunAsync Commander_Spawnee.Commander_Behaviour
  
  //dropship goes away after all SciFis jumped out
} On (event(SciFi_Multispawner.AllSpawned)) do {
  Send event Chapter003.AllSpawned
  
  //testing if all enemies from dropship are killed
} On (event all(DropshipsEnemies.AllKilled)) do {
  Wait (1.50 sec)
  Send event Chapter003.Finished
  ActionAsync Macro_Speech.StartFunction("DropshipSciFisDied")
  
} On (45.00 sec) do {
  //safety progress
  ActionAsync DetectorSafety.Recharge()
  Wait (event(DetectorSafety.Activated))
  Send event Chapter003.Finished
  
}



Macro Commander_Behaviour CLeggedCharacterEntity
{
  //Commander is first dropped from dropship, then it signals other soldiers to sorround player,  
  Wait (event(This.LastTacticMarkerReached))
  Action This.AttackMelee("Wave")
  Action This.macSetThreatSensitivity("Standard")
  
}
 