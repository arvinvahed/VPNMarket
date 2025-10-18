<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => ':attribute باید پذیرفته شود.',
    'accepted_if' => 'هنگامی که :other، :value است، :attribute باید پذیرفته شود.',
    'active_url' => ':attribute یک آدرس معتبر (URL) نیست.',
    'after' => ':attribute باید تاریخی بعد از :date باشد.',
    'after_or_equal' => ':attribute باید تاریخی بعد یا مساوی با :date باشد.',
    'alpha' => ':attribute فقط باید شامل حروف باشد.',
    'alpha_dash' => ':attribute فقط باید شامل حروف، اعداد، خط فاصله و زیرخط باشد.',
    'alpha_num' => ':attribute فقط باید شامل حروف و اعداد باشد.',
    'any_of' => ':attribute نامعتبر است.',
    'array' => ':attribute باید یک آرایه باشد.',
    'ascii' => ':attribute فقط باید شامل کاراکترها و نمادهای الفبایی-عددی یک بایتی باشد.',
    'before' => ':attribute باید تاریخی قبل از :date باشد.',
    'before_or_equal' => ':attribute باید تاریخی قبل یا مساوی با :date باشد.',
    'between' => [
        'array' => ':attribute باید بین :min و :max آیتم داشته باشد.',
        'file' => ':attribute باید بین :min و :max کیلوبایت باشد.',
        'numeric' => ':attribute باید بین :min و :max باشد.',
        'string' => ':attribute باید بین :min و :max کاراکتر باشد.',
    ],
    'boolean' => ':attribute باید درست یا غلط باشد.',
    'can' => ':attribute شامل یک مقدار غیرمجاز است.',
    'confirmed' => 'تایید :attribute مطابقت ندارد.',
    'contains' => ':attribute شامل مقدار مورد نیاز نیست.',
    'current_password' => 'رمز عبور صحیح نیست.',
    'date' => ':attribute یک تاریخ معتبر نیست.',
    'date_equals' => ':attribute باید یک تاریخ مساوی با :date باشد.',
    'date_format' => ':attribute با الگوی :format مطابقت ندارد.',
    'decimal' => ':attribute باید :decimal رقم اعشار داشته باشد.',
    'declined' => ':attribute باید رد شود.',
    'declined_if' => 'هنگامی که :other، :value است، :attribute باید رد شود.',
    'different' => ':attribute و :other باید متفاوت باشند.',
    'digits' => ':attribute باید :digits رقم باشد.',
    'digits_between' => ':attribute باید بین :min و :max رقم باشد.',
    'dimensions' => ':attribute ابعاد تصویر نامعتبر دارد.',
    'distinct' => ':attribute دارای یک مقدار تکراری است.',
    'doesnt_contain' => ':attribute نباید شامل هیچ یک از موارد زیر باشد: :values.',
    'doesnt_end_with' => ':attribute نباید با هیچ یک از موارد زیر پایان یابد: :values.',
    'doesnt_start_with' => ':attribute نباید با هیچ یک از موارد زیر شروع شود: :values.',
    'email' => ':attribute باید یک آدرس ایمیل معتبر باشد.',
    'ends_with' => ':attribute باید با یکی از موارد زیر پایان یابد: :values.',
    'enum' => ':attribute انتخاب شده نامعتبر است.',
    'exists' => ':attribute انتخاب شده نامعتبر است.',
    'extensions' => ':attribute باید دارای یکی از پسوندهای زیر باشد: :values.',
    'file' => ':attribute باید یک فایل باشد.',
    'filled' => ':attribute باید دارای یک مقدار باشد.',
    'gt' => [
        'array' => ':attribute باید بیشتر از :value آیتم داشته باشد.',
        'file' => ':attribute باید بزرگتر از :value کیلوبایت باشد.',
        'numeric' => ':attribute باید بزرگتر از :value باشد.',
        'string' => ':attribute باید بیشتر از :value کاراکتر باشد.',
    ],
    'gte' => [
        'array' => ':attribute باید :value آیتم یا بیشتر داشته باشد.',
        'file' => ':attribute باید بزرگتر یا مساوی :value کیلوبایت باشد.',
        'numeric' => ':attribute باید بزرگتر یا مساوی :value باشد.',
        'string' => ':attribute باید بزرگتر یا مساوی :value کاراکتر باشد.',
    ],
    'hex_color' => ':attribute باید یک کد رنگ هگزادسیمال معتبر باشد.',
    'image' => ':attribute باید یک تصویر باشد.',
    'in' => ':attribute انتخاب شده نامعتبر است.',
    'in_array' => ':attribute باید در :other وجود داشته باشد.',
    'in_array_keys' => ':attribute باید حداقل شامل یکی از کلیدهای زیر باشد: :values.',
    'integer' => ':attribute باید یک عدد صحیح (Integer) باشد.',
    'ip' => ':attribute باید یک آدرس IP معتبر باشد.',
    'ipv4' => ':attribute باید یک آدرس معتبر IPv4 باشد.',
    'ipv6' => ':attribute باید یک آدرس معتبر IPv6 باشد.',
    'json' => ':attribute باید یک رشته JSON معتبر باشد.',
    'list' => ':attribute باید یک لیست باشد.',
    'lowercase' => ':attribute باید حروف کوچک باشد.',
    'lt' => [
        'array' => ':attribute باید کمتر از :value آیتم داشته باشد.',
        'file' => ':attribute باید کمتر از :value کیلوبایت باشد.',
        'numeric' => ':attribute باید کمتر از :value باشد.',
        'string' => ':attribute باید کمتر از :value کاراکتر باشد.',
    ],
    'lte' => [
        'array' => ':attribute نباید بیشتر از :value آیتم داشته باشد.',
        'file' => ':attribute باید کمتر یا مساوی :value کیلوبایت باشد.',
        'numeric' => ':attribute باید کمتر یا مساوی :value باشد.',
        'string' => ':attribute باید کمتر یا مساوی :value کاراکتر باشد.',
    ],
    'mac_address' => ':attribute باید یک آدرس MAC معتبر باشد.',
    'max' => [
        'array' => ':attribute نباید بیشتر از :max آیتم داشته باشد.',
        'file' => ':attribute نباید بزرگتر از :max کیلوبایت باشد.',
        'numeric' => ':attribute نباید بزرگتر از :max باشد.',
        'string' => ':attribute نباید بیشتر از :max کاراکتر باشد.',
    ],
    'max_digits' => ':attribute نباید بیشتر از :max رقم داشته باشد.',
    'mimes' => ':attribute باید یک فایل از نوع: :values باشد.',
    'mimetypes' => ':attribute باید یک فایل از نوع: :values باشد.',
    'min' => [
        'array' => ':attribute باید حداقل :min آیتم داشته باشد.',
        'file' => ':attribute باید حداقل :min کیلوبایت باشد.',
        'numeric' => ':attribute باید حداقل :min باشد.',
        'string' => ':attribute باید حداقل :min کاراکتر باشد.',
    ],
    'min_digits' => ':attribute باید حداقل :min رقم داشته باشد.',
    'missing' => ':attribute باید وجود نداشته باشد.',
    'missing_if' => 'هنگامی که :other، :value است، :attribute باید وجود نداشته باشد.',
    'missing_unless' => 'مگر اینکه :other، :value باشد، در این صورت :attribute باید وجود نداشته باشد.',
    'missing_with' => 'هنگامی که :values وجود دارد، :attribute باید وجود نداشته باشد.',
    'missing_with_all' => 'هنگامی که همه :values وجود دارند، :attribute باید وجود نداشته باشد.',
    'multiple_of' => ':attribute باید مضربی از :value باشد.',
    'not_in' => ':attribute انتخاب شده نامعتبر است.',
    'not_regex' => 'فرمت :attribute نامعتبر است.',
    'numeric' => ':attribute باید یک عدد باشد.',
    'password' => [
        'letters' => ':attribute باید حداقل شامل یک حرف باشد.',
        'mixed' => ':attribute باید حداقل شامل یک حرف بزرگ و یک حرف کوچک باشد.',
        'numbers' => ':attribute باید حداقل شامل یک عدد باشد.',
        'symbols' => ':attribute باید حداقل شامل یک نماد باشد.',
        'uncompromised' => ':attribute داده شده در یک نشت اطلاعاتی ظاهر شده است. لطفاً :attribute دیگری را انتخاب کنید.',
    ],
    'present' => ':attribute باید وجود داشته باشد.',
    'present_if' => 'هنگامی که :other، :value است، :attribute باید وجود داشته باشد.',
    'present_unless' => 'مگر اینکه :other، :value باشد، در این صورت :attribute باید وجود داشته باشد.',
    'present_with' => 'هنگامی که :values وجود دارد، :attribute باید وجود داشته باشد.',
    'present_with_all' => 'هنگامی که همه :values وجود دارند، :attribute باید وجود داشته باشد.',
    'prohibited' => ':attribute ممنوع است.',
    'prohibited_if' => 'هنگامی که :other، :value است، :attribute ممنوع است.',
    'prohibited_if_accepted' => 'هنگامی که :other پذیرفته شده است، :attribute ممنوع است.',
    'prohibited_if_declined' => 'هنگامی که :other رد شده است، :attribute ممنوع است.',
    'prohibited_unless' => 'مگر اینکه :other در :values باشد، در این صورت :attribute ممنوع است.',
    'prohibits' => ':attribute مانع از حضور :other می‌شود.',
    'regex' => 'فرمت :attribute نامعتبر است.',
    'required' => 'فیلد :attribute الزامی است.',
    'required_array_keys' => 'فیلد :attribute باید شامل ورودی‌های: :values باشد.',
    'required_if' => 'هنگامی که :other، :value است، فیلد :attribute الزامی است.',
    'required_if_accepted' => 'هنگامی که :other پذیرفته شده است، فیلد :attribute الزامی است.',
    'required_if_declined' => 'هنگامی که :other رد شده است، فیلد :attribute الزامی است.',
    'required_unless' => 'مگر اینکه :other در :values باشد، در این صورت فیلد :attribute الزامی است.',
    'required_with' => 'هنگامی که :values وجود دارد، فیلد :attribute الزامی است.',
    'required_with_all' => 'هنگامی که همه :values وجود دارند، فیلد :attribute الزامی است.',
    'required_without' => 'هنگامی که :values وجود ندارد، فیلد :attribute الزامی است.',
    'required_without_all' => 'هنگامی که هیچ یک از :values وجود ندارند، فیلد :attribute الزامی است.',
    'same' => ':attribute و :other باید مشابه باشند.',
    'size' => [
        'array' => ':attribute باید شامل :size آیتم باشد.',
        'file' => ':attribute باید :size کیلوبایت باشد.',
        'numeric' => ':attribute باید :size باشد.',
        'string' => ':attribute باید :size کاراکتر باشد.',
    ],
    'starts_with' => ':attribute باید با یکی از موارد زیر شروع شود: :values.',
    'string' => ':attribute باید یک رشته باشد.',
    'timezone' => ':attribute باید یک منطقه زمانی معتبر باشد.',
    'unique' => ':attribute قبلاً انتخاب شده است.',
    'uploaded' => 'آپلود :attribute با شکست مواجه شد.',
    'uppercase' => ':attribute باید حروف بزرگ باشد.',
    'url' => ':attribute باید یک آدرس معتبر (URL) باشد.',
    'ulid' => ':attribute باید یک ULID معتبر باشد.',
    'uuid' => ':attribute باید یک UUID معتبر باشد.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'پیام سفارشی',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [
        'data.active_theme' => 'قالب اصلی سایت',
        'data.active_auth_theme' => 'قالب صفحات ورود/ثبت‌نام',
        'data.site_logo' => 'لوگوی سایت',

        'data.marzban_host' => 'آدرس پنل مرزبان',
        'data.marzban_sudo_username' => 'نام کاربری ادمین',
        'data.marzban_sudo_password' => 'رمز عبور ادمین',

        'data.marzneshin_host' => 'آدرس پنل مرزنشین',
        'data.marzneshin_sudo_username' => 'نام کاربری ادمین مرزنشین',
        'data.marzneshin_sudo_password' => 'رمز عبور ادمین مرزنشین',
        'data.marzneshin_node_hostname' => 'آدرس دامنه/سرور مرزنشین',

        'data.telegram_bot_token' => 'توکن ربات تلگرام',
        'data.telegram_admin_chat_id' => 'چت آی‌دی ادمین',

        // فیلدهای عمومی
        'email' => 'ایمیل',
        'password' => 'رمز عبور',
        'name' => 'نام',
    ],

];
