<?php

declare(strict_types=1);

use App\Support\SimBriefPlanHtml;

function planFixture(): string
{
    /** @var array{text: array{plan_html: string}} $data */
    $data = json_decode((string) file_get_contents(base_path('tests/data/simbrief/briefing.json')), true, flags: JSON_THROW_ON_ERROR);

    return $data['text']['plan_html'];
}

it("splits the real OFP into SimBrief's own top-level sections", function (): void {
    $titles = array_column(SimBriefPlanHtml::sections(planFixture()), 'title');

    expect($titles)->toBe([
        'OFP',
        'ATC Flight Plan',
        'Additional Info',
        'Runway Analysis',
        'Airport WX List',
        'NOTAM',
        'Company NOTAM',
    ]);
});

it('keeps level-1 markers inside their parent section rather than making tabs', function (): void {
    // "Summary and Fuel" etc. are ///1 markers nested under the ///0 "OFP"
    // section. Splitting on every marker would turn one tab into six.
    $sections = SimBriefPlanHtml::sections(planFixture());
    $titles = array_column($sections, 'title');

    expect($titles)->not->toContain('Summary and Fuel')
        ->and($sections[0]['html'])->toContain('PLANNED FUEL');
});

it('strips the bookmark comments from the rendered output', function (): void {
    foreach (SimBriefPlanHtml::sections(planFixture()) as $section) {
        expect($section['html'])->not->toContain('BKMK');
    }
});

it('preserves the provider formatting tags it is meant to render', function (): void {
    $first = SimBriefPlanHtml::sections(planFixture())[0]['html'];

    expect($first)->toContain('<b>');
});

it('removes a script tag hidden in the plan html', function (): void {
    $sections = SimBriefPlanHtml::sections(
        '<pre><!--BKMK///OFP///0-->SAFE<script>alert(1)</script></pre>',
    );

    expect($sections[0]['html'])->toContain('SAFE')
        ->and($sections[0]['html'])->not->toContain('<script')
        ->and($sections[0]['html'])->not->toContain('alert(1)');
});

it('removes inline event handlers', function (): void {
    $sections = SimBriefPlanHtml::sections(
        '<pre><!--BKMK///OFP///0--><b onerror="alert(1)" onclick="alert(2)">TEXT</b></pre>',
    );

    expect($sections[0]['html'])->toContain('TEXT')
        ->and($sections[0]['html'])->not->toContain('onerror')
        ->and($sections[0]['html'])->not->toContain('onclick');
});

it('drops a javascript: link target but keeps its text', function (): void {
    $sections = SimBriefPlanHtml::sections(
        '<pre><!--BKMK///OFP///0--><a href="javascript:alert(1)">CLICK</a></pre>',
    );

    expect($sections[0]['html'])->toContain('CLICK')
        ->and($sections[0]['html'])->not->toContain('javascript:');
});

it('drops an image with a data: source', function (): void {
    $sections = SimBriefPlanHtml::sections(
        '<pre><!--BKMK///OFP///0-->KEEP<img src="data:text/html,<script>alert(1)</script>"></pre>',
    );

    expect($sections[0]['html'])->toContain('KEEP')
        ->and($sections[0]['html'])->not->toContain('data:');
});

it('keeps a provider image served over https', function (): void {
    $sections = SimBriefPlanHtml::sections(
        '<pre><!--BKMK///OFP///0--><img src="https://www.simbrief.com/x.gif" alt="Route"></pre>',
    );

    expect($sections[0]['html'])->toContain('https://www.simbrief.com/x.gif')
        ->and($sections[0]['html'])->toContain('alt="Route"');
});

it('forces external links to disown the opener', function (): void {
    $sections = SimBriefPlanHtml::sections(
        '<pre><!--BKMK///OFP///0--><a href="https://example.com/a.gif">MAP</a></pre>',
    );

    expect($sections[0]['html'])->toContain('rel="noopener noreferrer"')
        ->and($sections[0]['html'])->toContain('target="_blank"');
});

it('returns no sections for an empty plan', function (): void {
    expect(SimBriefPlanHtml::sections(''))->toBe([])
        ->and(SimBriefPlanHtml::sections('<pre>   </pre>'))->toBe([]);
});

it('falls back to a single section when the plan carries no markers', function (): void {
    $sections = SimBriefPlanHtml::sections('<pre>PLAIN OFP BODY</pre>');

    expect($sections)->toHaveCount(1)
        ->and($sections[0]['title'])->toBe('Text OFP')
        ->and($sections[0]['html'])->toContain('PLAIN OFP BODY');
});
