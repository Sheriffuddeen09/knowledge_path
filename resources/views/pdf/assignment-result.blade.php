@if(
    isset($result->assignment) &&
    isset($result->assignment->questions) &&
    count($result->assignment->questions) > 0
)

    @foreach($result->assignment->questions as $index => $question)
        @php
            $answer = $result->answers->firstWhere('question_id', $question->id);
        @endphp

        <div class="box">
            <p><strong>{{ $index + 1 }}. {{ $question->question }}</strong></p>

            @foreach(['A','B','C','D'] as $opt)
                @php
                    $isCorrect = $question->correct_answer === $opt;
                    $isChosen = optional($answer)->selected_answer === $opt;
                @endphp

                <p class="
                    {{ $isCorrect ? 'correct' : '' }}
                    {{ $isChosen && !$isCorrect ? 'wrong' : '' }}
                ">
                    {{ $opt }}. {{ $question->{'option_'.strtolower($opt)} }}
                </p>
            @endforeach

            {{-- Show correct answer only if wrong --}}
            @if($answer && $answer->selected_answer !== $question->correct_answer)
                <p class="correct">
                    ✅ Correct answer:
                    {{ $question->correct_answer }}.
                    {{ $question->{'option_'.strtolower($question->correct_answer)} }}
                </p>
            @endif
        </div>
    @endforeach

@else
    <p>No questions available for this assignment.</p>
@endif
