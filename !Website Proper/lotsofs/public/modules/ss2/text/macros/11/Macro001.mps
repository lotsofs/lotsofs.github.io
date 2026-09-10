Macro Main ?
{
  Wait (event(Chapter001.Started))
  RunAsync This.FirstChapter
}

Macro FirstChapter ?
{
  global var CAircraftCharacterEntity Fighter
  
  ActionAsync WorldInfo.ForceMusic("Continuous",Cheerful_Music)
  
  Action SP_Shotgun.SpawnSimple()
  ActionAsync Items.SpawnSimple()
  
  ActionAsync SP_Simba001.SpawnSimple()
  ActionAsync SP_Simba002.SpawnSimple()
  ActionAsync SP_Simba003.SpawnSimple()
  
  //start flyby fighters
  ActionAsync Fighter_Flyby_01.SpawnOne()
  Wait (2.50 sec)
  ActionAsync Fighter_Flyby_02.SpawnOne()
  Wait (0.50 sec)
  ActionAsync Fighter_Flyby_01.SpawnOne()
  Wait (0.50 sec)
  ActionAsync Fighter_Flyby_02.SpawnOne()
  
  Wait (event(Chapter001.Finished))
  
} On (event(DetectorArea003.Activated)) do {
  Send event ChapterControl.StartChapter002
  
} On (every (event(Fighter01.SpawneeAvailable))) do {
  Fighter=Fighter01.GetLastSpawned()
  
} On (every (event(DetectorFighter.Activated))) do {
  ActionAsync Fighter.AttackShoot("Bombs",6,TRUE)
  Wait (2.00 sec)
  ActionAsync DetectorFighter.Recharge()
  
} On (event(SP_Simba001.SpawneeAvailable)) do {
  Simba01=SP_Simba001.GetLastSpawned()
  Run Simba01.Simba001_Behavior
  Run Simba01.LookTarget
  
} On (event(SP_Simba002.SpawneeAvailable)) do {
  Simba01=SP_Simba002.GetLastSpawned()
  Run Simba01.Simba001_Behavior
  Run Simba01.LookTarget
  
} On (event(SP_Simba003.SpawneeAvailable)) do {
  Simba01=SP_Simba003.GetLastSpawned()
  Run Simba01.Simba001_Behavior
  Run Simba01.LookTarget
  
} On (event(FirstSciFi.SpawneeAvailable)) do {
  global var CLeggedCharacterEntity SciFi1st
  SciFi1st=FirstSciFi.GetLastSpawned()
  
} On (event(DetectorArea_SimbaPanic.Activated)) do {
  Action SimbaChild.SpawnSimple()
  Wait (0.10 sec)
  Action SimbaFemale.SpawnSimple()
  Wait (2.50 sec)
  ActionAsync WorldInfo.ForceMusic("Tense")
  
} On (event(DetectorArea002.Activated)) do {
  Action SciFi_02.SpawnSimple()
  
} On (every (event(SimbaFemale.SpawneeAvailable))) do {
  global var CLeggedPuppetEntity SimbaFemalePanic
  SimbaFemalePanic=SimbaFemale.GetLastSpawned()
  ActionAsync SimbaFemalePanic.BeInvulnerable(TRUE)
  Run SimbaFemalePanic.SimbaPanic_Behavior
  
} On (every (event(SimbaChild.SpawneeAvailable))) do {
  global var CLeggedPuppetEntity SimbaChildPanic
  SimbaChildPanic=SimbaChild.GetLastSpawned()
  ActionAsync SimbaChildPanic.BeInvulnerable(TRUE)
  Run SimbaChildPanic.SimbaPanic_Behavior
  
} On (every (event(SciFi_02.SpawneeAvailable))) do {
  SciFi02=SciFi_02.GetLastSpawned()
  Run SciFi02.SciFi02_Behavior
  
} On (event(SciFi_02.AllKilled)) do {
  Send event Chapter001.SciFisKilled
  ActionAsync WorldInfo.ForceMusic("Relaxing")
  
} On (event(DetectorArea003.Activated)) do {
  Send event Chapter001.Finished
}


//Simbas point at Kwongo and run into their houses

Macro Simba001_Behavior CLeggedCharacterEntity
{
  ActionAsync This.PatrolMarkers()
  Wait (4 times (event(This.MarkerReached)))
  
  ActionAsync This.StopPatrolingMarkers()
  Action This.PlayAnimLoop("Gesture_Speach04")
  Wait (2.75 sec)
  Action This.StopAnim()
  Action This.ContinuePatrolingMarkers()
  
  Wait (event(Chapter001.Finished))
  
  
} On (every (event(This.LastPatrolMarkerReached))) do {
  Action This.StopPatrolingMarkers()
  Action This.ChangeState("Default")
  
}



Macro SciFi02_Behavior CLeggedCharacterEntity
{
  Action This.PatrolMarkers(Marker02)
  Wait (3 times (event(This.MarkerReached)))
  Action This.macSetThreatSensitivity("Standard")
}



Macro SimbaPanic_Behavior CLeggedPuppetEntity
{
  Wait (event(This.LastTacticMarkerReached))
  
} On (every (3.00 sec)) do {
  ActionAsync This.PlaySchemeSound("Panic")
  ActionAsync This.PlayAnim("Body_Tool_ThrowChest")
}



 