import React, { useState } from 'react';

// Utilisation Twig : <div {{ react_component('AnimalFilters', { species: 'cat' }) }}></div>
export default function AnimalFilters({ species = 'cat', onChange }) {
    const [current, setCurrent] = useState(species);
    const [radius, setRadius] = useState(30);

    const update = (next) => {
        const value = { species: current, radius, ...next };
        if (next.species) setCurrent(next.species);
        if (next.radius) setRadius(next.radius);
        if (onChange) onChange(value);
    };

    return (
        <form className="animal-filters" onSubmit={(e) => e.preventDefault()}>
            <label>
                Espèce
                <select value={current} onChange={(e) => update({ species: e.target.value })}>
                    <option value="cat">Chat</option>
                    <option value="dog">Chien</option>
                </select>
            </label>
            <label>
                Rayon : {radius} km
                <input
                    type="range" min="5" max="100" step="5" value={radius}
                    onChange={(e) => update({ radius: Number(e.target.value) })}
                />
            </label>
        </form>
    );
}
