import * as React from 'react';

import { cn } from '@/lib/tiptap-utils';

import { Button } from '@/components/tiptap-ui-primitive/button';
import { Input } from '@/components/tiptap-ui-primitive/input';
import { Textarea } from '@/components/tiptap-ui-primitive/textarea';

import '@/components/tiptap-ui-primitive/input-group/input-group.scss';

export type InputGroupAddonAlign =
    | 'inline-start'
    | 'inline-end'
    | 'block-start'
    | 'block-end';
export type InputGroupButtonSize = 'xs' | 'sm' | 'icon-xs' | 'icon-sm';

/**
 * A frame around one control and its addons. Focus, validation, and
 * disabled states are read from the children and drawn on the frame.
 */
function InputGroup({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="tiptap-input-group"
            role="group"
            className={cn('tiptap-input-group', className)}
            {...props}
        />
    );
}

/**
 * Icons, text, keyboard hints, or buttons beside the control. `align` maps
 * to `data-align`: inline addons sit at either end of the row, block addons
 * take a full row above or below. Clicking the addon focuses the control
 * unless the click landed on a button inside it.
 */
function InputGroupAddon({
    className,
    align = 'inline-start',
    ...props
}: React.ComponentProps<'div'> & { align?: InputGroupAddonAlign }) {
    return (
        <div
            role="group"
            data-slot="tiptap-input-group-addon"
            data-align={align}
            className={cn('tiptap-input-group-addon', className)}
            onClick={(event) => {
                if ((event.target as HTMLElement).closest('button')) {
                    return;
                }
                event.currentTarget.parentElement
                    ?.querySelector('input')
                    ?.focus();
            }}
            {...props}
        />
    );
}

/**
 * A tiptap Button sized for the frame. `xs` and `icon-xs` are a step below
 * the button's small size; `sm` and `icon-sm` keep its default height.
 */
function InputGroupButton({
    className,
    type = 'button',
    variant = 'ghost',
    size = 'xs',
    ...props
}: Omit<React.ComponentProps<typeof Button>, 'size' | 'type'> & {
    size?: InputGroupButtonSize;
    type?: 'button' | 'submit' | 'reset';
}) {
    return (
        <Button
            type={type}
            data-size={size}
            variant={variant}
            className={cn('tiptap-input-group-button', className)}
            {...props}
        />
    );
}

function InputGroupText({ className, ...props }: React.ComponentProps<'span'>) {
    return (
        <span
            data-slot="tiptap-input-group-text"
            className={cn('tiptap-input-group-text', className)}
            {...props}
        />
    );
}

function InputGroupInput({
    className,
    ...props
}: React.ComponentProps<'input'>) {
    return (
        <Input
            data-slot="tiptap-input-group-control"
            className={cn('tiptap-input-group-control', className)}
            {...props}
        />
    );
}

function InputGroupTextarea({
    className,
    ...props
}: React.ComponentProps<'textarea'>) {
    return (
        <Textarea
            data-slot="tiptap-input-group-control"
            className={cn('tiptap-input-group-control', className)}
            {...props}
        />
    );
}

export {
    InputGroup,
    InputGroupAddon,
    InputGroupButton,
    InputGroupText,
    InputGroupInput,
    InputGroupTextarea,
};
