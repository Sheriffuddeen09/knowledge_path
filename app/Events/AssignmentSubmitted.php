class AssignmentSubmitted implements ShouldBroadcast
{
    public $assignmentId;
    public $student;

    public function __construct($assignmentId, $student)
    {
        $this->assignmentId = $assignmentId;
        $this->student = $student;
    }

    public function broadcastOn()
    {
        return new Channel("assignment.$this->assignmentId");
    }
}


