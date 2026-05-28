export default function scrollToErrors(node) {
	if (node.props.type === 'form') {
		function scrollTo(node) {
			const el = document.getElementById(node.props.id);
			if (el) {
				el.scrollIntoView({
					behavior: 'smooth',
					block: 'end',
					inline: 'nearest',
				});
			}
		}

		function scrollToErrors() {
			node.walk((child) => {
				// Check if this child has errors
				if (child.ledger.value('blocking') || child.ledger.value('errors')) {
					// We found an input with validation errors
					scrollTo(child);
					// Stop searching
					return false;
				}
			}, true);
		}

		const onSubmitInvalid = node.props.onSubmitInvalid;
		node.props.onSubmitInvalid = () => {
			// onSubmitInvalid(node);
			scrollToErrors();
		};
		node.on('unsettled:errors', scrollToErrors);
	}
	return false;
}
