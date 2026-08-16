import { useCallback, useRef } from 'react';
import { useFocusEffect } from '@react-navigation/native';

type LoadFn = (refresh?: boolean) => void | Promise<void>;

/**
 * Recharge les données au premier affichage (avec écran de chargement),
 * puis silencieusement à chaque retour sur l'écran (sans repasser par le
 * chargement complet, pour éviter que l'app "se recharge" à chaque navigation).
 */
export function useFocusLoad(load: LoadFn) {
  const loaded = useRef(false);
  const loadRef = useRef(load);
  loadRef.current = load;

  useFocusEffect(
    useCallback(() => {
      const refresh = loaded.current;
      loaded.current = true;
      void loadRef.current(refresh);
    }, [])
  );
}
