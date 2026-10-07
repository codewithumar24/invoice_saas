@extends('layouts.guest')
@section('title', __($title))

{{--
    STARTER TEXT. These pages are a sensible starting point, not legal advice.
    Have them reviewed for every jurisdiction where you sell before going live,
    and replace the bracketed placeholders with your company details.
--}}
@php
    $app = config('app.name');
    $email = platform_setting('support_email') ?: '[your contact email]';
    $updated = '[date of last update]';
@endphp

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-16 sm:px-6">
        <h1 class="font-serif text-4xl font-semibold tracking-tight">{{ __($title) }}</h1>
        <p class="caption mt-2">{{ __('Last updated: :date', ['date' => $updated]) }}</p>

        <div class="prose-basic mt-6">
            @switch($page)
                @case('terms')
                    <p>These terms are an agreement between you and [company legal name] ("we"), the operator of {{ $app }}. By creating an account you accept them.</p>
                    <h2>The service</h2>
                    <p>{{ $app }} is online software for invoicing and business management. We provide it on a subscription basis according to the plan you choose.</p>
                    <h2>Your account</h2>
                    <p>You are responsible for the people you invite to your workspace, for keeping passwords secret and for the accuracy of the information you enter. You must be able to form a binding contract in your country.</p>
                    <h2>Your data</h2>
                    <p>The business data you enter belongs to you. You give us the permission needed to store and process it to provide the service. You can export it at any time and request deletion of your workspace.</p>
                    <h2>Payment</h2>
                    <p>Paid plans are billed in advance for each period. If a payment fails we may limit the workspace until it is settled. Prices can change with prior notice for the next period.</p>
                    <h2>Acceptable use</h2>
                    <p>You agree to follow the <a href="{{ route('legal.acceptable-use') }}">Acceptable Use Policy</a>.</p>
                    <h2>Availability and liability</h2>
                    <p>We work to keep the service available and your data safe, but the service is provided "as is". To the extent the law allows, our liability is limited to the fees you paid in the twelve months before the claim. {{ $app }} is a record-keeping tool; projections and health scores are estimates and not financial, tax or legal advice.</p>
                    <h2>Ending the agreement</h2>
                    <p>You can cancel at any time from your billing settings. We may suspend accounts that break these terms. On termination your data is deleted after the grace period described in the Privacy Policy, except where the law requires us to keep it.</p>
                    <h2>Governing law</h2>
                    <p>These terms are governed by the laws of [country / state]. Contact: {{ $email }}.</p>
                    @break

                @case('privacy')
                    <p>This policy explains what personal data [company legal name] collects through {{ $app }}, why, and what your rights are.</p>
                    <h2>Data we collect</h2>
                    <ul>
                        <li><strong>Account data:</strong> name, email, password (stored only as a hash), country, timezone.</li>
                        <li><strong>Business data you enter:</strong> customers, invoices, payments, expenses, products and files. For this data you are the controller and we are your processor.</li>
                        <li><strong>Security data:</strong> sign-in attempts, IP address, browser and session records, audit log.</li>
                        <li><strong>Cookies:</strong> see the <a href="{{ route('legal.cookies') }}">Cookie Policy</a>.</li>
                    </ul>
                    <h2>Why we use it</h2>
                    <p>To provide and secure the service, to bill subscriptions, to send service messages, and, only with your consent, to measure how the public website is used.</p>
                    <h2>Sharing</h2>
                    <p>We do not sell personal data. We share it only with service providers that help us run the service (hosting, email delivery, payment processing) under contracts that protect it, and when the law requires it.</p>
                    <h2>Retention</h2>
                    <p>Account and business data is kept while the workspace exists. After a deletion request there is a 30-day grace period, after which data is deleted or anonymised. Backups expire after their retention period. Financial records may be kept longer where the law requires.</p>
                    <h2>Your rights</h2>
                    <p>Depending on where you live you can ask to access, correct, export or delete your personal data, and object to or restrict some processing. Write to {{ $email }}.</p>
                    <h2>International transfers and security</h2>
                    <p>Data may be processed in [countries / regions]. We use encryption in transit, hashed passwords, access controls, audit logging and backups. See <a href="{{ route('legal.security') }}">Security</a>.</p>
                    @break

                @case('cookies')
                    <p>Cookies are small files stored by your browser. {{ $app }} uses as few as possible.</p>
                    <h2>Necessary cookies</h2>
                    <p>Always on. They keep you signed in, protect forms against forgery (CSRF) and remember your cookie choice. The service cannot work without them.</p>
                    <h2>Analytics cookies</h2>
                    <p>Off unless you allow them. They help us understand which pages of the public website are used.</p>
                    <h2>Marketing cookies</h2>
                    <p>Off unless you allow them. They measure the results of our campaigns.</p>
                    <h2>Changing your choice</h2>
                    <p>Use "Cookie preferences" at the bottom of any page to change or withdraw your consent at any time.</p>
                    @break

                @case('refund-policy')
                    <p>This policy explains when [company legal name] refunds subscription payments for {{ $app }}.</p>
                    <h2>Trials and the free plan</h2>
                    <p>You can use the free plan or a trial without paying, so you can evaluate the service before you buy.</p>
                    <h2>Refunds</h2>
                    <p>If you are not satisfied, contact us within [14] days of your first payment for a full refund. Renewal payments are refundable within [7] days if the paid period has not been used. Other refunds are considered case by case.</p>
                    <h2>Cancelling</h2>
                    <p>Cancelling stops future charges. You keep access until the end of the period already paid.</p>
                    <h2>How to ask</h2>
                    <p>Write to {{ $email }} from the email address of the workspace owner.</p>
                    @break

                @case('acceptable-use')
                    <p>To keep {{ $app }} safe for everyone, you agree not to:</p>
                    <ul>
                        <li>use the service for unlawful activity, fraud or to issue false invoices;</li>
                        <li>send spam or unsolicited messages through the email features;</li>
                        <li>upload malware or content that infringes the rights of others;</li>
                        <li>attempt to access data of other workspaces, probe or attack the service, or bypass limits;</li>
                        <li>resell the service without a white-label agreement;</li>
                        <li>overload the API beyond the published rate limits.</li>
                    </ul>
                    <p>We may suspend accounts that break this policy. Report abuse to {{ $email }}.</p>
                    @break

                @case('data-processing')
                    <p>When you store personal data of your own customers in {{ $app }}, you are the data controller and [company legal name] is your data processor.</p>
                    <h2>What we process and why</h2>
                    <p>Contact and transaction details of your customers and team, only to provide the service to you and on your instructions.</p>
                    <h2>Our commitments</h2>
                    <ul>
                        <li>Process the data only as needed to provide the service.</li>
                        <li>Keep it confidential and limit access to staff who need it; support access to a workspace is recorded in the audit log.</li>
                        <li>Apply the security measures described on the Security page.</li>
                        <li>Tell you without undue delay about a personal data breach affecting your workspace.</li>
                        <li>Help you answer requests from data subjects through the export and deletion tools.</li>
                        <li>Delete or return the data when the service ends.</li>
                    </ul>
                    <h2>Sub-processors</h2>
                    <p>[List hosting, email and payment providers here.] A signed data processing agreement is available on request: {{ $email }}.</p>
                    @break

                @case('security')
                    <p>Your financial records deserve care. This is how {{ $app }} protects them.</p>
                    <h2>Separation of businesses</h2>
                    <p>Every business is a separate workspace. Each request is limited to its own workspace on the server, and this is covered by automated tests.</p>
                    <h2>Access</h2>
                    <ul>
                        <li>Passwords are stored only as salted hashes.</li>
                        <li>Two-factor authentication with authenticator apps and backup codes; a workspace can require it for everyone.</li>
                        <li>Roles and permissions decide what each team member can see and do, enforced on the server.</li>
                        <li>Sign-in attempts are rate limited and recorded; new sign-ins trigger an alert; you can sign out all devices.</li>
                    </ul>
                    <h2>Data</h2>
                    <ul>
                        <li>Encrypted connections (HTTPS) and secure, HTTP-only cookies.</li>
                        <li>Uploaded files are stored privately and every download is authorised.</li>
                        <li>Payment gateway keys are stored encrypted. Online payments are confirmed only by signed gateway webhooks.</li>
                        <li>Sensitive changes are written to an audit log that cannot be edited.</li>
                        <li>Regular backups with a defined retention period.</li>
                    </ul>
                    <h2>Reporting a problem</h2>
                    <p>If you believe you have found a security issue, write to {{ $email }}.</p>
                    @break
            @endswitch
        </div>
    </div>
@endsection
