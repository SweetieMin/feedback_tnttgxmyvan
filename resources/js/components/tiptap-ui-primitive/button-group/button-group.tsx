import { mergeProps } from '@base-ui/react/merge-props';
import { useRender } from '@base-ui/react/use-render';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/tiptap-utils';
import { Separator } from '@/components/tiptap-ui-primitive/separator';
import './button-group.scss';

const buttonGroupVariants = cva('tiptap-button-group', {
    variants: {
        variant: {
            default: '',
            segmented: 'tiptap-segmented-control',
        },
        orientation: {
            horizontal: 'tiptap-button-group-horizontal',
            vertical: 'tiptap-button-group-vertical',
        },
    },
    defaultVariants: {
        variant: 'default',
        orientation: 'horizontal',
    },
});

/** The segmented variant reads selection from data-active, aria-pressed, or aria-selected. */
function ButtonGroup({
    className,
    orientation,
    variant,
    ...props
}: React.ComponentProps<'div'> & VariantProps<typeof buttonGroupVariants>) {
    return (
        <div
            role="group"
            data-slot="tiptap-button-group"
            data-orientation={orientation}
            className={cn(
                buttonGroupVariants({ orientation, variant }),
                className,
            )}
            {...props}
        />
    );
}

function ButtonGroupText({
    className,
    render,
    ...props
}: useRender.ComponentProps<'div'>) {
    return useRender({
        defaultTagName: 'div',
        props: mergeProps<'div'>(
            { className: cn('tiptap-button-group-text', className) },
            props,
        ),
        render,
        state: { slot: 'tiptap-button-group-text' },
    });
}

function ButtonGroupSeparator({
    className,
    orientation = 'vertical',
    ...props
}: React.ComponentProps<typeof Separator>) {
    return (
        <Separator
            data-slot="tiptap-button-group-separator"
            orientation={orientation}
            className={cn('tiptap-button-group-separator', className)}
            {...props}
        />
    );
}

export {
    ButtonGroup,
    ButtonGroupSeparator,
    ButtonGroupText,
    buttonGroupVariants,
};
