<h2>{{ $result->assignment->title }}</h2>

<p>
Score: {{ $result->score }} / {{ $result->total_questions }}
<br>
Ratio: {{ round(($result->score / $result->total_questions) * 100, 1) }}%
</p>

<hr>

@foreach ($result->answers as $a)
    <p>
        <strong>{{ $a->question->question }}</strong><br>
        Your Answer: {{ $a->selected_answer }} <br>
        Correct: {{ $a->question->correct_answer }}
    </p>
@endforeach
