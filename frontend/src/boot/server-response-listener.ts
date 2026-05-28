// import { tabId } from "@/boot/apollo";
import { Notify } from 'quasar'

const eventSource = new EventSource(
	'http://localhost/.well-known/mercure?topic=error', //_tab' + tabId,
)

eventSource.onmessage = (event) => {
	// merror(JSON.parse(event.data));

	show({
		message: JSON.parse(event.data),
		type: 'negative',
		timeout: 0,
	})
}

function show(arg) {
	const temp = {
		type: arg.type,
		multiLine: true,
		textColor: 'surface-900',
		actions: [
			{
				icon: 'sym_o_close',
				color: 'dark',
				round: true,
				handler: () => {
					/* ... */
				},
			},
		],
	}
	if (typeof arg == 'object') {
		Notify.create({
			...temp,
			...arg,
			...(typeof arg.message == 'object' ? arg.message : {}),
		})
		return
	}
	Notify.create({ ...temp, message: arg })
}
