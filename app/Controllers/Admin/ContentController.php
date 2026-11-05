<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\HttpException;
use App\Models\AuditLog;
use App\Models\Faq;
use App\Models\Testimonial;

/**
 * Public content the storefront reads: testimonials (the "reviews" screen) and
 * the FAQ page.
 *
 * Every column written here is validated against the real schema before it reaches
 * a model, and every create / update / delete is stamped in the audit trail. The
 * delete routes are already behind owner middleware (routes/web.php), so a role
 * check in this file would be dead code; what stays here is the in-page
 * confirm=1 guard that protects an accidental submit.
 */
final class ContentController extends Controller
{
    public function testimonials(Request $request): Response
    {
        unset($request);

        return $this->view('admin.reviews', [
            'testimonials' => (new Testimonial())->allForAdmin(),
        ]);
    }

    public function storeTestimonial(Request $request): Response
    {
        $data = $this->validate($request, $this->testimonialRules(), $this->testimonialLabels());
        $testimonials = new Testimonial();

        $id = $testimonials->create($this->testimonialRow($data));

        AuditLog::record('testimonial.created', 'testimonial', $id, sprintf(
            'Added a %d-star testimonial from "%s" for %s.',
            (int) $data['rating'],
            mb_substr((string) $data['name'], 0, 60),
            (string) ($data['service_label'] ?? 'a service')
        ));

        return $this->redirect(route('admin.testimonials'), 'success', 'The testimonial was added.');
    }

    public function updateTestimonial(Request $request, string $id): Response
    {
        $testimonialId = (int) $id;
        $testimonials = new Testimonial();

        if ($testimonials->find($testimonialId) === null) {
            throw new HttpException(404, 'That testimonial does not exist.');
        }

        $data = $this->validate($request, $this->testimonialRules(), $this->testimonialLabels());
        $testimonials->update($testimonialId, $this->testimonialRow($data));

        AuditLog::record('testimonial.updated', 'testimonial', $testimonialId, sprintf(
            'Updated testimonial #%d from "%s".',
            $testimonialId,
            mb_substr((string) $data['name'], 0, 60)
        ));

        return $this->redirect(route('admin.testimonials'), 'success', 'The testimonial was saved.');
    }

    public function deleteTestimonial(Request $request, string $id): Response
    {
        $testimonialId = (int) $id;
        $testimonials = new Testimonial();
        $testimonial = $testimonials->find($testimonialId);

        if ($testimonial === null) {
            throw new HttpException(404, 'That testimonial does not exist.');
        }

        if ((string) $request->input('confirm', '') !== '1') {
            return $this->redirect(route('admin.testimonials'), 'error', 'Tick the confirmation box before deleting a testimonial.');
        }

        $testimonials->delete($testimonialId);

        AuditLog::record('testimonial.deleted', 'testimonial', $testimonialId, sprintf(
            'Deleted testimonial #%d from "%s".',
            $testimonialId,
            mb_substr((string) ($testimonial['name'] ?? ''), 0, 60)
        ));

        return $this->redirect(route('admin.testimonials'), 'success', 'The testimonial was deleted.');
    }

    public function faqs(Request $request): Response
    {
        unset($request);

        $rows = (new Faq())->allForAdmin();

        $categories = [];
        foreach ($rows as $row) {
            $category = trim((string) ($row['category'] ?? 'general'));
            $categories[$category] = true;
        }

        return $this->view('admin.faqs', [
            'faqs' => $rows,
            'categories' => array_keys($categories),
        ]);
    }

    public function storeFaq(Request $request): Response
    {
        $data = $this->validate($request, $this->faqRules(), $this->faqLabels());
        $faqs = new Faq();

        $row = $this->faqRow($data);
        $id = $faqs->create($row);

        AuditLog::record('faq.created', 'faq', $id, sprintf(
            'Added FAQ "%s" in category "%s".',
            mb_substr((string) $data['question'], 0, 80),
            (string) $row['category']
        ));

        return $this->redirect(route('admin.faqs'), 'success', 'The FAQ was added.');
    }

    public function updateFaq(Request $request, string $id): Response
    {
        $faqId = (int) $id;
        $faqs = new Faq();

        if ($faqs->find($faqId) === null) {
            throw new HttpException(404, 'That FAQ does not exist.');
        }

        $data = $this->validate($request, $this->faqRules(), $this->faqLabels());
        $faqs->update($faqId, $this->faqRow($data));

        AuditLog::record('faq.updated', 'faq', $faqId, sprintf(
            'Updated FAQ #%d "%s".',
            $faqId,
            mb_substr((string) $data['question'], 0, 80)
        ));

        return $this->redirect(route('admin.faqs'), 'success', 'The FAQ was saved.');
    }

    public function deleteFaq(Request $request, string $id): Response
    {
        $faqId = (int) $id;
        $faqs = new Faq();
        $faq = $faqs->find($faqId);

        if ($faq === null) {
            throw new HttpException(404, 'That FAQ does not exist.');
        }

        if ((string) $request->input('confirm', '') !== '1') {
            return $this->redirect(route('admin.faqs'), 'error', 'Tick the confirmation box before deleting a FAQ.');
        }

        $faqs->delete($faqId);

        AuditLog::record('faq.deleted', 'faq', $faqId, sprintf(
            'Deleted FAQ #%d "%s".',
            $faqId,
            mb_substr((string) ($faq['question'] ?? ''), 0, 80)
        ));

        return $this->redirect(route('admin.faqs'), 'success', 'The FAQ was deleted.');
    }

    /** @return array<string, string> */
    private function testimonialRules(): array
    {
        return [
            'name' => 'required|max:80',
            'location' => 'max:60',
            'service_label' => 'max:60',
            'rating' => 'required|integer|between:1,5',
            'quote' => 'required|max:400',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return array<string, string> */
    private function testimonialLabels(): array
    {
        return [
            'name' => 'Name',
            'location' => 'Location',
            'service_label' => 'Service',
            'rating' => 'Rating',
            'quote' => 'Quote',
            'sort_order' => 'Sort order',
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function testimonialRow(array $data): array
    {
        $location = trim((string) ($data['location'] ?? ''));
        $service = trim((string) ($data['service_label'] ?? ''));

        return [
            'name' => (string) $data['name'],
            'location' => $location === '' ? null : $location,
            'service_label' => $service === '' ? null : $service,
            'rating' => (int) $data['rating'],
            'quote' => (string) $data['quote'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (int) ($data['is_active'] ?? 0),
        ];
    }

    /** @return array<string, string> */
    private function faqRules(): array
    {
        return [
            'question' => 'required|max:200',
            'answer' => 'required|max:1000',
            'category' => 'max:40',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return array<string, string> */
    private function faqLabels(): array
    {
        return [
            'question' => 'Question',
            'answer' => 'Answer',
            'category' => 'Category',
            'sort_order' => 'Sort order',
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function faqRow(array $data): array
    {
        $category = trim((string) ($data['category'] ?? ''));

        return [
            'question' => (string) $data['question'],
            'answer' => (string) $data['answer'],
            'category' => $category === '' ? 'general' : mb_strtolower($category),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (int) ($data['is_active'] ?? 0),
        ];
    }
}
