import { registerBlockType } from "@wordpress/blocks";
import { InspectorControls, useBlockProps } from "@wordpress/block-editor";
import { PanelBody, Placeholder, SelectControl, Spinner } from "@wordpress/components";
import { useEffect, useState } from "@wordpress/element";
import apiFetch from "@wordpress/api-fetch";
import ServerSideRender from "@wordpress/server-side-render";
import { __ } from "@wordpress/i18n";

import metadata from "./block.json";

const TEMPLATE_OPTIONS = [
	{ label: __("Default", "maqsy-faq-ai-chat"), value: "" },
	{ label: __("Simple", "maqsy-faq-ai-chat"), value: "simple" },
	{ label: __("Accordion", "maqsy-faq-ai-chat"), value: "accordion" },
];

function Edit({ attributes, setAttributes }) {
	const { postId, template } = attributes;
	const [faqs, setFaqs] = useState([]);
	const [isLoading, setIsLoading] = useState(true);
	const blockProps = useBlockProps();

	useEffect(() => {
		apiFetch({ path: "/maqsy/v1/faqs" })
			.then((results) => {
				setFaqs(results);
				setIsLoading(false);
			})
			.catch(() => setIsLoading(false));
	}, []);

	const faqOptions = [
		{ label: __("Select a FAQ…", "maqsy-faq-ai-chat"), value: 0 },
		...faqs.map((faq) => ({
			label: "publish" === faq.status ? faq.title : `${faq.title} (${faq.status})`,
			value: faq.id,
		})),
	];

	return (
		<div {...blockProps}>
			<InspectorControls>
				<PanelBody title={__("FAQ Settings", "maqsy-faq-ai-chat")}>
					<SelectControl
						label={__("FAQ", "maqsy-faq-ai-chat")}
						value={postId}
						options={faqOptions}
						onChange={(value) => setAttributes({ postId: Number(value) })}
						disabled={isLoading}
					/>
					<SelectControl
						label={__("Template", "maqsy-faq-ai-chat")}
						value={template}
						options={TEMPLATE_OPTIONS}
						onChange={(value) => setAttributes({ template: value })}
					/>
				</PanelBody>
			</InspectorControls>
			{!postId ? (
				<Placeholder
					icon="sos"
					label={__("FAQ", "maqsy-faq-ai-chat")}
					instructions={__("Select a FAQ to display from the block settings.", "maqsy-faq-ai-chat")}
				>
					{isLoading && <Spinner />}
				</Placeholder>
			) : (
				<ServerSideRender block={metadata.name} attributes={attributes} />
			)}
		</div>
	);
}

registerBlockType(metadata.name, {
	edit: Edit,
	save: () => null,
});
