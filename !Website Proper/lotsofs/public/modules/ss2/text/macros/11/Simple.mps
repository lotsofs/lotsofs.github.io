Macro Main ?
{
  global var INDEX varPlate
  
  varPlate=0
  
  Action Motor.SetForceY(5500)
  Action Motor.SetPositionY(0)
  Action Motor.SetVelocityY(10.00)
  Wait (1.00 sec)
  Action DetectorDown.RegisterCustomDetection(Plate)
  Wait (event(Chapter002.ForceOpen))
  
} On (every (event(DetectorDown.CustomDetection))) do {
  Action DetectorUp.Recharge()
  Action DetectorUp.RegisterCustomDetection(Plate)
  Send event This.PlateDown
  
} On (every (event(DetectorUp.CustomDetection))) do {
  Action DetectorDown.Recharge()
  Action DetectorDown.RegisterCustomDetection(Plate)
  Send event This.PlateUp
  
} On (every (event(This.PlateDown))) do {
  varPlate=1
  Action Doors.Open()
  
} On (every (event(This.PlateUp))) do {
  varPlate=0
  Action Doors.Lock()
  
} On (event(Shotgun.OnePicked)) do {
  Run This.SafetyTimer
  
}


Macro SafetyTimer ?
{
  Wait (event(Chapter002.Started))
  
} On (30.00 sec) do {
  If (varPlate==0) {
    ActionAsync Macro_SpeechControl.StartFunction("MechanismAdvice")
  }
  
} On (60.00 sec) do {
  Send event Chapter002.ForceOpen
  Action Doors.Open()
  
}

 