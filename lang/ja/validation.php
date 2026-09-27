<?php
// lang/ja/validation.php

return [
    'required' => ':attributeを入力してください。',
    'required_without' => ':attributeを入力してください。',
    'string' => ':attributeは文字列で入力してください。',
    'integer' => ':attributeは数値で入力してください。',
    'boolean' => ':attributeの指定が正しくありません。',
    'email' => ':attributeはメールアドレスの形式で入力してください。',
    'confirmed' => ':attributeが確認用と一致しません。',
    'unique' => 'その:attributeはすでに使われています。',
    'exists' => '選択された:attributeが正しくありません。',
    'in' => '選択された:attributeが正しくありません。',
    'date' => ':attributeは日付の形式で入力してください。',
    'date_format' => ':attributeは:format の形式で入力してください。',
    'after' => ':attributeは:dateより後にしてください。',
    'numeric' => ':attributeは数値で入力してください。',
    'between' => [
        'numeric' => ':attributeは:minから:maxの間で指定してください。',
    ],
    'size' => [
        'string' => ':attributeは:size文字で入力してください。',
    ],
    'max' => [
        'string' => ':attributeは:max文字以内で入力してください。',
        'numeric' => ':attributeは:max以下にしてください。',
    ],
    'min' => [
        'string' => ':attributeは:min文字以上で入力してください。',
        'numeric' => ':attributeは:min以上にしてください。',
    ],

    'attributes' => [
        'name' => 'おなまえ',
        'email' => 'メールアドレス',
        'password' => 'パスワード',
        'password_confirmation' => 'パスワード（確認）',
        'title' => 'タイトル',
        'place' => '場所',
        'date' => '日付',
        'start_time' => 'はじまる時間',
        'end_time' => 'おわる時間',
        'starts_on' => '通いはじめた日',
        'ends_on' => 'おわる日',
        'day_of_week' => '曜日',
        'member_id' => 'だれの',
        'pickup_user_id' => 'お迎え担当',
        'due_date' => '期限',
        'invite_code' => '招待コード',
        'color' => '色',
        'note' => 'メモ',
    ],
];