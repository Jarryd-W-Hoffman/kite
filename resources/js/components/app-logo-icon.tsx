import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 40 42" xmlns="http://www.w3.org/2000/svg">
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M20 1 36 15 20 31 4 15 20 1Zm0 4.6L8.6 15H19V5.6Zm2 0V15h9.4L22 5.6ZM8.6 17 19 27.4V17H8.6Zm13.4 0v10.4L31.4 17H22Zm-2.6 16.2c1.4 1.6 1.6 3 .8 4.3-.6 1-1.7 1.6-3 1.9l.4 2c1.9-.4 3.5-1.3 4.4-2.9 1.3-2.1.8-4.4-1.1-6.6l-1.5 1.3Z"
            />
        </svg>
    );
}
