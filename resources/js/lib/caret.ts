const MIRRORED_PROPERTIES = [
    'boxSizing',
    'width',
    'height',
    'overflowX',
    'overflowY',
    'borderTopWidth',
    'borderRightWidth',
    'borderBottomWidth',
    'borderLeftWidth',
    'paddingTop',
    'paddingRight',
    'paddingBottom',
    'paddingLeft',
    'fontStyle',
    'fontVariant',
    'fontWeight',
    'fontStretch',
    'fontSize',
    'lineHeight',
    'fontFamily',
    'textAlign',
    'textTransform',
    'textIndent',
    'letterSpacing',
    'wordSpacing',
    'tabSize',
    'whiteSpace',
    'wordBreak',
    'overflowWrap',
] as const;

/**
 * Measure where a character offset sits inside a textarea, relative to its top-left corner.
 */
export function caretCoordinates(
    textarea: HTMLTextAreaElement,
    position: number,
): { top: number; left: number; height: number } {
    const mirror = document.createElement('div');
    const style = window.getComputedStyle(textarea);

    for (const property of MIRRORED_PROPERTIES) {
        mirror.style[property] = style[property];
    }

    mirror.style.position = 'absolute';
    mirror.style.visibility = 'hidden';
    mirror.style.whiteSpace = 'pre-wrap';
    mirror.style.overflowWrap = 'break-word';
    mirror.style.top = '0';
    mirror.style.left = '-9999px';
    mirror.textContent = textarea.value.slice(0, position);

    const marker = document.createElement('span');
    marker.textContent = textarea.value.slice(position) || '.';
    mirror.append(marker);

    document.body.append(mirror);

    const lineHeight = Number.parseFloat(style.lineHeight) || 20;
    const coordinates = {
        top: marker.offsetTop - textarea.scrollTop,
        left: marker.offsetLeft - textarea.scrollLeft,
        height: lineHeight,
    };

    mirror.remove();

    return coordinates;
}
