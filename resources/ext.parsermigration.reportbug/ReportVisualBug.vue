<template>
	<cdx-dialog
		v-model:open="open"
		title="placeholder"
		:class="stepClass"
		:use-close-button="true"
		:stacked-actions="stackedActions"
	>
		<template #header>
			<div v-if="currentStep < 2" class="cdx-dialog__header__title-group">
				<h2
					v-i18n-html:parsermigration-reportbug-dialog-title
					class="cdx-dialog__header__title"></h2>
				<p
					v-i18n-html:parsermigration-reportbug-dialog-desc
					class="cdx-dialog__header__subtitle"></p>
			</div>
			<div v-else class="cdx-dialog__header__title-group">
				<h2
					v-i18n-html:parsermigration-reportbug-dialog-complete-title
					class="cdx-dialog__header__title"></h2>
			</div>
			<cdx-button
				class="cdx-dialog__header__close-button"
				weight="quiet"
				type="button"
				:aria-label="$i18n( 'parsermigration-reportbug-dialog-cancel-button-label' )"
				@click="onCancel"
			>
				<cdx-icon :icon="cdxIconClose"></cdx-icon>
			</cdx-button>
		</template>

		<template v-if="currentStep < 2">
			<cdx-field>
				<template #label>
					{{
						$i18n(
							'parsermigration-reportbug-dialog-message-label',
							pageName, revisionId
						)
					}}
				</template>
				<cdx-text-area
					v-model="message"
					class="parsermigration-reportbug-dialog-input"
					autosize
					:placeholder="$i18n( 'parsermigration-reportbug-dialog-placeholder' )"
					:disabled="currentStep > 0"
				></cdx-text-area>
			</cdx-field>

			<cdx-message
				v-if="error"
				type="error"
				class="parsermigration-reportbug-dialog-error"
			>
				<p>
					<strong
						v-i18n-html:parsermigration-reportbug-dialog-error-label
					></strong>
					{{ error }}
				</p>
			</cdx-message>

			<div><!--keeps bottom padding of error from being squashed--></div>
		</template>

		<template v-else-if="currentStep === 2">
			<p
				v-i18n-html:parsermigration-reportbug-dialog-complete-message="[ feedbackTitle, feedbackUrl ]"
			></p>
		</template>

		<template #footer>
			<p
				v-if="currentStep < 2 && !error"
				v-i18n-html:parsermigration-reportbug-dialog-destination="[ feedbackTitle, feedbackUrl ]"
				class="parsermigration-reportbug-dialog-destination"
			></p>

			<div
				class="cdx-dialog__footer__actions"
			>
				<cdx-progress-indicator
					v-if="currentStep === 1"
					show-label
					class="parsermigration-reportbug-dialog-progress"
				>
					<div
						v-i18n-html:parsermigration-reportbug-dialog-progress
					></div>
				</cdx-progress-indicator>

				<cdx-button
					v-if="primaryAction"
					class="cdx-dialog__footer__primary-action"
					weight="primary"
					:action="primaryAction.actionType"
					:disabled="primaryAction.disabled"
					@click="onSubmit"
				>
					{{ primaryAction.label }}
				</cdx-button>

				<cdx-button
					v-if="defaultAction"
					class="cdx-dialog__footer__default-action"
					:action="defaultAction.actionType"
					:disabled="defaultAction.disabled"
					:weight="defaultAction.weight"
					@click="onCancel"
				>
					{{ defaultAction.label }}
				</cdx-button>
			</div>
		</template>
	</cdx-dialog>
</template>

<script>
const { defineComponent, computed, ref, onMounted, onUnmounted } = require( 'vue' );
const { cdxIconClose } = require( './icons.json' );
const {
	CdxButton, CdxDialog, CdxField, CdxIcon, CdxMessage,
	CdxProgressIndicator, CdxTextArea
} = require( '../codex.js' );

module.exports = defineComponent( {
	name: 'ReportVisualBugDialog',
	components: {
		CdxButton,
		CdxDialog,
		CdxField,
		CdxIcon,
		CdxMessage,
		CdxProgressIndicator,
		CdxTextArea
	},
	emits: [ 'submit' ],
	setup( props, { emit, expose } ) {
		const open = ref( false );
		const error = ref( '' );

		const message = ref( '' );
		const currentStep = ref( 0 );

		const stepClass = computed( () => {
			const classes = {
				'parsermigration-reportbug-dialog': true
			};
			classes[ `parsermigration-reportbug-dialog-step${ currentStep.value }` ] = true;
			return classes;
		} );

		const primaryAction = computed( () => {
			// Primary action is "submit", but only available at first step.
			if ( currentStep.value < 2 ) {
				return {
					label: mw.msg( error.value ?
						'parsermigration-reportbug-dialog-retry-button-label' :
						'parsermigration-reportbug-dialog-submit-button-label' ),
					actionType: 'progressive',
					disabled: currentStep.value > 0 || !message.value
				};
			}
			// no primary action after submission
			return null;
		} );

		const defaultAction = computed( () => {
			// Default action is "cancel" until we reach the 'success' state
			// (step 2); at which point it switches to 'done'.
			if ( currentStep.value < 2 ) {
				return {
					label: mw.msg( 'parsermigration-reportbug-dialog-cancel-button-label' )
				};
			} else {
				return {
					label: mw.msg( 'parsermigration-reportbug-dialog-done-button-label' ),
					weight: 'primary'
				};
			}
		} );

		const pageName = ref( '' );
		const revisionId = ref( 0 );
		const feedbackTitle = ref( '' );
		const feedbackUrl = ref( '//' );
		const isMobile = ref( false );

		// Reactive variable tracking window width
		const windowWidth = ref( window.innerWidth );
		const onWindowResize = () => {
			windowWidth.value = window.innerWidth;
		};
		onMounted( () => window.addEventListener( 'resize', onWindowResize ) );
		onUnmounted( () => window.removeEventListener( 'resize', onWindowResize ) );

		const stackedActions = computed( () => isMobile.value || windowWidth.value <= 512 );

		const onSubmit = () => {
			currentStep.value = 1;
			error.value = '';
			emit( 'submit', pageName.value, revisionId.value, message.value );
		};

		const onCancel = () => {
			open.value = false;
		};

		const start = ( pageName_, revisionId_ ) => {
			pageName.value = pageName_;
			revisionId.value = revisionId_;
			currentStep.value = 0;
			message.value = '';
			error.value = '';
			open.value = true;
		};

		const reportSuccess = () => {
			currentStep.value = 2;
		};

		const reportFailure = ( errorMessage ) => {
			currentStep.value = 0;
			error.value = errorMessage;
		};

		expose( {
			feedbackTitle,
			feedbackUrl,
			isMobile,
			start,
			reportSuccess,
			reportFailure
		} );

		return {
			open,
			error,
			currentStep,
			stepClass,
			message,
			feedbackTitle,
			feedbackUrl,
			pageName,
			revisionId,
			stackedActions,
			defaultAction,
			primaryAction,
			cdxIconClose,
			onSubmit,
			onCancel
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.parsermigration-reportbug-dialog {

	header {
		display: flex;
		align-items: baseline;
		justify-content: flex-end;
		box-sizing: @box-sizing-base;
		width: @size-full;
	}

	&-step0 footer,
	&-step1 footer {
		padding-top: 0px;
	}

	&-progress {
		flex: auto;
	}

	&.cdx-dialog--horizontal-actions &-progress {
		order: 1;
	}

	&-error {
		margin-top: 8px;
	}

	&-destination, &-progress {
		color: @color-subtle;
	}
}

@media screen and ( max-width: 270px ) {
	/* Hide progress message when the screen is very narrow. */
	.parsermigration-reportbug-dialog-progress .cdx-label { display: none; }
}
</style>
