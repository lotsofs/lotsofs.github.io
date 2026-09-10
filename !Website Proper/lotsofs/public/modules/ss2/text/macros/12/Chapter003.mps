Macro Chapter003 ?
{
  Wait (event(Chapter003.Started))
  Wait (0.10 sec)
  RunAsync This.ActivateSimbaSequence
  RunAsync This.Fight
  ActionAsync MacroSimbaVictim.StartFunction("Main")
}


Macro ActivateSimbaSequence ?
{
  Wait (event(Chapter003.Finished))
  
} On (event(Detector_Island.Activated)) do {
  RunAsync This.FighterOrder
  
}



Macro Fight ?
{
  ActionAsync Fight_06_Field.Recharge()
  Wait (1.00 sec)
  ActionAsync Simba_Range_Field_04.Recharge()
  Wait (event(Chapter003.Finished))
  
} On (event(Fight_06_Field.Activated)) do {
  ActionAsync SP_Neanderthal_04.SpawnMaintainGroup()
  Wait (7.00 sec)
  ActionAsync SP_Barbarian_03.SpawnMaintainGroup()
  ActionAsync SP_Barbarian_04.SpawnMaintainGroup()
  
} On (event(SP_Neanderthal_04.GroupKilled)) do {
  ActionAsync SP_Lizard_02.SpawnMaintainGroup()
  
} On (event(Detector_Fighter.Activated)) do {
  FighterVillage=Fighter_OverVillage.SpawnOne()
  RunAsync FighterVillage.FighterBombingVillage
  
} On (event all(Fight_06_All_Apawners.AllKilled)) do {
  Send event ChapterControl.StartChapter004
  
} On (event(DetectorChapter004.Activated)) do {
  Send event ChapterControl.StartChapter004
  
}



Macro FighterOrder CAircraftCharacterEntity
{
  global var CAircraftCharacterEntity Fighter01
  global var CAircraftCharacterEntity Fighter02
  
  ActionAsync DetectorBombAttack.Recharge()
  Wait (1.00 sec)
  Fighter01=Fighter_01.SpawnOne()
  RunAsync Fighter01.FighterOneRound
  Wait (7.00 sec)
  Fighter02=Fighter_02.SpawnOne()
  RunAsync Fighter02.FighterOneRound
  Wait (False)
  
} On (event(DetectorBombAttack.Activated)) do {
  ActionAsync Fighter01.AttackShoot("Bombs",5,TRUE)
  
}


Macro FighterBombingVillage CAircraftCharacterEntity
{
  ActionAsync Detector_Bombing.Recharge()
  Wait (False)
  
} On (every (event(Detector_Bombing.Activated))) do {
  ActionAsync This.AttackShoot("Bombs",6,TRUE)
  Wait (2.00 sec)
  ActionAsync Detector_Bombing.Recharge()
  
}


Macro FighterOneRound CAircraftCharacterEntity
{
  Wait (False)
  
} On (5 times (event(This.TacticMarkerReached))) do {
  ActionAsync This.Disappear()
  
}
 