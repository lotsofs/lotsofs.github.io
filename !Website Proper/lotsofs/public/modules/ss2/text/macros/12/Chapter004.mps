Macro Chapter004 ?
{
  Wait (event(Chapter004.Started))
  Run This.Fight
}


Macro Fight ?
{
  global var CLeggedCharacterEntity Neanderthal05
  
  
  Action Tower_Simbas.SpawnSimple()
  ActionAsync SP_Neanderthal_05.SpawnSimple()
  RunAsync This.Neanderthal_06_Wall_Smasher
  ActionAsync Barbarian_04.SpawnSimple()
  ActionAsync Neanderthal_Sense_Field.Recharge()
  ActionAsync Vilage_Door_Field.Recharge()
  Wait (event(This.EndChapter004))
  
} On (every (event(SP_Neanderthal_05.SpawneeAvailable))) do {
  Neanderthal05=SP_Neanderthal_05.GetLastSpawned()
  fRnd=macRnd()*1
  Wait (fRnd sec)
  RunAsync Neanderthal05.Neanderthal_05_Wall_Pusher
  
} On (every (event(Barbarian_04.SpawneeAvailable))) do {
  Spawnee=Barbarian_04.GetLastSpawned()
  RunAsync Spawnee.Barbarian_Sense
  
  
} On (event(Detector_Fighter.Activated)) do {
  Fighter=Fighter_OverVillage.SpawnOne()
  RunAsync Fighter.FighterBombingVillage
  
} On (event(Detector02_Fighter.Activated)) do {
  Fighter=Fighter_OverVillage.SpawnOne()
  RunAsync Fighter.FighterBombingVillage
  
} On (event all(Fight_07_SP.AllKilled)) do {
  ActionAsync Vilage_Door_Field.Recharge()
  
} On ((event all(Fight_07_SP.AllKilled)) and then (event(Vilage_Door_Field.Activated))) do {
  Send event ChapterControl.StartNextLevelLink
  Send event This.EndChapter004
}




Macro FighterBombingVillage CAircraftCharacterEntity
{
  ActionAsync Detector_Bombing.Recharge()
  Wait (False)
  
} On (every (event(Detector_Bombing.Activated))) do {
  ActionAsync This.AttackShoot("Bombs",2,TRUE)
  Wait (2.00 sec)
  ActionAsync Detector_Bombing.Recharge()
  
}


Macro Barbarian_Sense CLeggedCharacterEntity
{
  ActionAsync This.ReportDamage()
  Wait (False)
  
} On (event any(Neanderthal_Sense_Field.Activated, This.Damaged, Chapter004.Activate_other_neanderthal_Sense)) do {
  If (This!=NULL) {
    ActionAsync This.macSetThreatSensitivity("Standard")
    ActionAsync This.PatrolMarkers(Attack_Marker)
    Send event Chapter004.Activate_other_neanderthal_Sense
  }
}


Macro Neanderthal_06_Wall_Smasher CLeggedCharacterEntity
{
  global var CLeggedCharacterEntity Neanderthal06
  global var CLeggedCharacterEntity Primitive
  Action SP_Neanderthal_06.SpawnSimple()
  Wait (2.00 sec)
  Wait (False)
  
} On (every (event(SP_Neanderthal_06.SpawneeAvailable))) do {
  Neanderthal06=SP_Neanderthal_06.GetLastSpawned()
  RunAsync Neanderthal06.Smash_Wall
  RunAsync Neanderthal06.DamageReport
}

Macro Smash_Wall CLeggedCharacterEntity
{
  fRnd=macRnd()*1
  Wait (fRnd sec)
  RunAsync This.Smash_Loop
}

Macro Smash_Loop CLeggedCharacterEntity
{
  Wait (event any(Neanderthal_Sense_Field.Activated, Chapter004.StartAttack, Chapter004.Activate_other_neanderthal_Sense))
} On (every (1.46 sec)) do {
  If (This!=NULL) {
    Action This.AttackMelee("Melee")
  }
}



Macro Neanderthal_05_Wall_Pusher CLeggedCharacterEntity
{
  ActionAsync This.ReportDamage()
  Send event Chapter004.Wall_Pusher_Start
  Wait (False)
  
} On (event(Chapter004.Wall_Pusher_Start)) do {
  RunAsync This.Push_Loop
  Wait (0.20 sec)
  Send event Chapter004.Wall_Pusher_Start
  
} On (event any(Neanderthal_Sense_Field.Activated, This.Damaged, Chapter004.Activate_other_neanderthal_Sense)) do {
  If (This!=NULL) {
    Action This.macSetThreatSensitivity("See through walls")
    Action This.PatrolMarkers(Attack_Marker)
    Send event Chapter004.Activate_other_neanderthal_Sense
  }
}


Macro Push_Loop ?
{
  Wait (event any(Neanderthal_Sense_Field.Activated, Chapter004.StartAttack, Chapter004.Activate_other_neanderthal_Sense))
} On (every (event(Chapter004.Wall_Pusher_Start))) do {
  If (This!=NULL) {
    Action This.AttackMelee("Melee")
    Send event Chapter004.Wall_Pusher_Start
  }
}



Macro DamageReport CLeggedCharacterEntity
{
  Action This.ReportDamage()
  Wait (event any(This.Damaged, Chapter004.StartAttack))
  Send event Chapter004.StartAttack
  
} On (event any(Neanderthal_Sense_Field.Activated, Chapter004.StartAttack, Chapter004.Activate_other_neanderthal_Sense)) do {
  If (This!=NULL) {
    Action This.macSetThreatSensitivity("See through walls")
    Action This.PatrolMarkers(Attack_Marker)
    Send event Chapter004.Activate_other_neanderthal_Sense
  }
}
 