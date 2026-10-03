<?php

namespace App\Support;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Inline\AbstractStringContainer;

class KnowledgebaseMarkdown
{
    /** @return array{html: string, headings: list<array{id: string, title: string}>} */
    public function render(string $content): array
    {
        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
            'heading_permalink' => ['insert' => 'none', 'apply_id_to_heading' => true, 'id_prefix' => 'guide'],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new HeadingPermalinkExtension);
        $result = (new MarkdownConverter($environment))->convert($content);
        $headings = [];

        foreach ($result->getDocument()->iterator() as $node) {
            if ($node instanceof Heading && $node->getLevel() <= 3) {
                $title = '';
                foreach ($node->iterator() as $child) {
                    if ($child instanceof AbstractStringContainer) {
                        $title .= $child->getLiteral();
                    }
                }
                $headings[] = ['id' => $node->data->get('attributes/id'), 'title' => $title];
            }
        }

        return ['html' => $result->getContent(), 'headings' => $headings];
    }
}
