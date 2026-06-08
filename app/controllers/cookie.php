<?php
/**
 * Cookie controller serves the cookie policy page.
 */
class Cookie extends TinyController
{
    /**
     * Compose landing page sections using aggregate and curated formation data.
     *
     * @param TinyRequest $request Incoming HTTP request context.
     * @param TinyResponse $response Response helper used to render a view.
     */
    public function get($request, $response)
    {
        $page = tiny::cms()->getPage('cookie.md');

        if (!$page) {
            $response->render(404);
            return;
        }

        if ($request->isMarkdownRequest()) {
            $response->sendMarkdown($page->raw, 200, true);
        }

        // Pass structured data to the home view; the view maps each section to UI components.
        $response->render('cookie', [
            'page' => $page,
        ]);
    }
}
