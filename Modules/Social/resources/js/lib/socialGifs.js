export async function searchGifs(query, page = 1, kind = 'gif') {
  const { data } = await window.axios.get('/api/social/gifs/search', {
    params: { q: query, page, kind },
  });
  return data;
}

export async function downloadGif(url) {
  const { data } = await window.axios.post('/api/social/gifs/download', { url });
  return data;
}
