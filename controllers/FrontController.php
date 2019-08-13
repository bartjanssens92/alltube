<?php
/**
 * FrontController class.
 */

namespace Alltube\Controller;

use Alltube\Exception\PasswordException;
use Alltube\Locale;
use Alltube\LocaleManager;
use Alltube\Video;
use Exception;
use Psr\Container\ContainerInterface;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\Smarty;

/**
 * Main controller.
 */
class FrontController extends BaseController
{
    /**
     * Smarty view.
     *
     * @var Smarty
     */
    private $view;

    /**
     * LocaleManager instance.
     *
     * @var LocaleManager
     */
    private $localeManager;

    /**
     * BaseController constructor.
     *
     * @param ContainerInterface $container Slim dependency container
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->localeManager = $this->container->get('locale');
        $this->view = $this->container->get('view');
    }

    /**
     * Display index page.
     *
     * @param Request  $request  PSR-7 request
     * @param Response $response PSR-7 response
     *
     * @return Response HTTP response
     */
    public function index(Request $request, Response $response)
    {
        $uri = $request->getUri()->withUserInfo('');
        $this->view->render(
            $response,
            'index.tpl',
            [
                'config'           => $this->config,
                'class'            => 'index',
                'description'      => _('Easily download videos from Youtube, Dailymotion, Vimeo and other websites.'),
                'domain'           => $uri->getScheme().'://'.$uri->getAuthority(),
                'canonical'        => $this->getCanonicalUrl($request),
                'supportedLocales' => $this->localeManager->getSupportedLocales(),
                'locale'           => $this->localeManager->getLocale(),
            ]
        );

        return $response;
    }

    /**
     * Switch locale.
     *
     * @param Request  $request  PSR-7 request
     * @param Response $response PSR-7 response
     * @param array    $data     Query parameters
     *
     * @return Response
     */
    public function locale(Request $request, Response $response, array $data)
    {
        $this->localeManager->setLocale(new Locale($data['locale']));

        return $response->withRedirect($this->container->get('router')->pathFor('index'));
    }

    /**
     * Display a list of extractors.
     *
     * @param Request  $request  PSR-7 request
     * @param Response $response PSR-7 response
     *
     * @return Response HTTP response
     */
    public function extractors(Request $request, Response $response)
    {
        $this->view->render(
            $response,
            'extractors.tpl',
            [
                'config'      => $this->config,
                'extractors'  => Video::getExtractors(),
                'class'       => 'extractors',
                'title'       => _('Supported websites'),
                'description' => _('List of all supported websites from which Alltube Download '.
                    'can extract video or audio files'),
                'canonical' => $this->getCanonicalUrl($request),
                'locale'    => $this->localeManager->getLocale(),
            ]
        );

        return $response;
    }

    /**
     * Display a password prompt.
     *
     * @param Request  $request  PSR-7 request
     * @param Response $response PSR-7 response
     *
     * @return Response HTTP response
     */
    public function password(Request $request, Response $response)
    {
        $this->view->render(
            $response,
            'password.tpl',
            [
                'config'      => $this->config,
                'class'       => 'password',
                'title'       => _('Password prompt'),
                'description' => _('You need a password in order to download this video with Alltube Download'),
                'canonical'   => $this->getCanonicalUrl($request),
                'locale'      => $this->localeManager->getLocale(),
            ]
        );

        return $response;
    }

    /**
     * Return the video description page.
     *
     * @param Request  $request  PSR-7 request
     * @param Response $response PSR-7 response
     *
     * @return Response HTTP response
     */
    private function getInfoResponse(Request $request, Response $response)
    {
        try {
            $this->video->getJson();
        } catch (PasswordException $e) {
            return $this->password($request, $response);
        }

        if (isset($this->video->entries)) {
            $template = 'playlist.tpl';
        } else {
            $template = 'info.tpl';
        }
        $title = _('Video download');
        $description = _('Download video from ').$this->video->extractor_key;
        if (isset($this->video->title)) {
            $title = $this->video->title;
            $description = _('Download').' "'.$this->video->title.'" '._('from').' '.$this->video->extractor_key;
        }
        $this->view->render(
            $response,
            $template,
            [
                'video'         => $this->video,
                'class'         => 'info',
                'title'         => $title,
                'description'   => $description,
                'config'        => $this->config,
                'canonical'     => $this->getCanonicalUrl($request),
                'locale'        => $this->localeManager->getLocale(),
                'defaultFormat' => $this->defaultFormat,
            ]
        );

        return $response;
    }

    /**
     * Dislay information about the video.
     *
     * @param Request  $request  PSR-7 request
     * @param Response $response PSR-7 response
     *
     * @return Response HTTP response
     */
    public function info(Request $request, Response $response)
    {
        $url = $request->getQueryParam('url') ?: $request->getQueryParam('v');

        if (isset($url) && !empty($url)) {
            $this->video = new Video($url, $this->getFormat($request), $this->getPassword($request));

            if ($this->config->convert && $request->getQueryParam('audio')) {
                // We skip the info page and get directly to the download.
                return $response->withRedirect(
                    $this->container->get('router')->pathFor('download').
                    '?'.http_build_query($request->getQueryParams())
                );
            } else {
                return $this->getInfoResponse($request, $response);
            }
        } else {
            return $response->withRedirect($this->container->get('router')->pathFor('index'));
        }
    }

    /**
     * Display an error page.
     *
     * @param Request   $request   PSR-7 request
     * @param Response  $response  PSR-7 response
     * @param Exception $exception Error to display
     *
     * @return Response HTTP response
     */
    public function error(Request $request, Response $response, Exception $exception)
    {
        $this->view->render(
            $response,
            'error.tpl',
            [
                'config'    => $this->config,
                'errors'    => $exception->getMessage(),
                'class'     => 'video',
                'title'     => _('Error'),
                'canonical' => $this->getCanonicalUrl($request),
                'locale'    => $this->localeManager->getLocale(),
            ]
        );

        return $response->withStatus(500);
    }

    /**
     * Get a video/audio stream piped through the server.
     *
     * @param string   $url      URL of the video
     * @param string   $format   Requested format
     * @param Response $response PSR-7 response
     * @param Request  $request  PSR-7 request
     * @param string   $password Video password
     *
     * @return Response HTTP response
     */
    private function getStream($url, $format, Response $response, Request $request, $password = null)
    {
        $video = $this->download->getJSON($url, $format, $password);
        if (isset($video->entries)) {
            $stream = $this->download->getPlaylistArchiveStream($video, $format);
            $response = $response->withHeader('Content-Type', 'application/x-tar');
            $response = $response->withHeader(
                'Content-Disposition',
                'attachment; filename="'.$video->title.'.tar"'
            );

            return $response->withBody(new Stream($stream));
        } elseif ($video->protocol == 'rtmp') {
            $stream = $this->download->getRtmpStream($video);
            $response = $response->withHeader('Content-Type', 'video/'.$video->ext);
            $body = new Stream($stream);
        } elseif ($video->protocol == 'm3u8' || $video->protocol == 'm3u8_native') {
            $stream = $this->download->getM3uStream($video);
            $response = $response->withHeader('Content-Type', 'video/'.$video->ext);
            $body = new Stream($stream);
        } else {
            $client = new Client();
            $stream = $client->request(
                'GET',
                $video->url,
                [
                    'stream'  => true,
                    'headers' => ['Range' => $request->getHeader('Range')],
                ]
            );
            $response = $response->withHeader('Content-Type', $stream->getHeader('Content-Type'));
            $response = $response->withHeader('Content-Length', $stream->getHeader('Content-Length'));
            $response = $response->withHeader('Accept-Ranges', $stream->getHeader('Accept-Ranges'));
            $response = $response->withHeader('Content-Range', $stream->getHeader('Content-Range'));
            if ($stream->getStatusCode() == 206) {
                $response = $response->withStatus(206);
            }
            $body = $stream->getBody();
        }
        if ($request->isGet()) {
            $response = $response->withBody($body);
        }
        $response = $response->withHeader(
            'Content-Disposition',
            'attachment; filename="'.
                $this->download->getFilename($url, $format, $password).'"'
        );

        return $response;
    }

    /**
     * Get a remuxed stream piped through the server.
     *
     * @param string[] $urls     URLs of the video and audio files
     * @param string   $format   Requested format
     * @param Response $response PSR-7 response
     * @param Request  $request  PSR-7 request
     *
     * @return Response HTTP response
     */
    private function getRemuxStream(array $urls, $format, Response $response, Request $request)
    {
        if (!$this->config->remux) {
            throw new Exception(_('You need to enable remux mode to merge two formats.'));
        }
        $stream = $this->download->getRemuxStream($urls);
        $response = $response->withHeader('Content-Type', 'video/x-matroska');
        if ($request->isGet()) {
            $response = $response->withBody(new Stream($stream));
        }
        $webpageUrl = $request->getQueryParam('url');

        return $response->withHeader(
            'Content-Disposition',
            'attachment; filename="'.$this->download->getFileNameWithExtension(
                'mkv',
                $webpageUrl,
                $format,
                $this->sessionSegment->getFlash($webpageUrl)
            )
        );
    }

    /**
     * Get video format from request parameters or default format if none is specified.
     *
     * @param Request $request PSR-7 request
     *
     * @return string format
     */
    private function getFormat(Request $request)
    {
        $format = $request->getQueryParam('format');
        if (!isset($format)) {
            $format = $this->defaultFormat;
        }

        return $format;
    }

    /**
     * Get approriate HTTP response to redirect query
     * Depends on whether we want to stream, remux or simply redirect.
     *
     * @param string   $url      URL of the video
     * @param string   $format   Requested format
     * @param Response $response PSR-7 response
     * @param Request  $request  PSR-7 request
     *
     * @return Response HTTP response
     */
    private function getRedirectResponse($url, $format, Response $response, Request $request)
    {
        try {
            $videoUrls = $this->download->getURL(
                $url,
                $format,
                $this->sessionSegment->getFlash($url)
            );
        } catch (EmptyUrlException $e) {
            /*
            If this happens it is probably a playlist
            so it will either be handled by getStream() or throw an exception anyway.
             */
            $videoUrls = [];
        }
        if (count($videoUrls) > 1) {
            return $this->getRemuxStream($videoUrls, $format, $response, $request);
        } elseif ($this->config->stream) {
            return $this->getStream(
                $url,
                $format,
                $response,
                $request,
                $this->sessionSegment->getFlash($url)
            );
        } else {
            if (empty($videoUrls[0])) {
                throw new Exception(_("Can't find URL of video."));
            }

            return $response->withRedirect($videoUrls[0]);
        }
    }

    /**
     * Return a converted video file.
     *
     * @param Request  $request  PSR-7 request
     * @param Response $response PSR-7 response
     * @param array    $params   GET query parameters
     * @param string   $format   Requested source format
     *
     * @return Response HTTP response
     */
    private function getConvertedResponse(Request $request, Response $response, array $params, $format)
    {
        $password = $request->getParam('password');
        $response = $response->withHeader(
            'Content-Disposition',
            'attachment; filename="'.
            $this->download->getFileNameWithExtension(
                $params['customFormat'],
                $params['url'],
                $format,
                $password
            ).'"'
        );
        $response = $response->withHeader('Content-Type', 'video/'.$params['customFormat']);

        if ($request->isGet() || $request->isPost()) {
            $process = $this->download->getConvertedStream(
                $params['url'],
                $format,
                $params['customBitrate'],
                $params['customFormat'],
                $password
            );
            $response = $response->withBody(new Stream($process));
        }

        return $response;
    }

    /**
     * Redirect to video file.
     *
     * @param Request  $request  PSR-7 request
     * @param Response $response PSR-7 response
     *
     * @return Response HTTP response
     */
    public function redirect(Request $request, Response $response)
    {
        $params = $request->getQueryParams();
        $format = $this->getFormat($request);
        if (isset($params['url'])) {
            try {
                if ($this->config->convertAdvanced && !is_null($request->getQueryParam('customConvert'))) {
                    return $this->getConvertedResponse($request, $response, $params, $format);
                }

                return $this->getRedirectResponse($params['url'], $format, $response, $request);
            } catch (PasswordException $e) {
                return $response->withRedirect(
                    $this->container->get('router')->pathFor('video').'?url='.urlencode($params['url'])
                );
            } catch (Exception $e) {
                $response->getBody()->write($e->getMessage());

                return $response->withHeader('Content-Type', 'text/plain')->withStatus(500);
            }
        } else {
            return $response->withRedirect($this->container->get('router')->pathFor('index'));
        }
    }

    /**
     * Return the JSON object generated by youtube-dl.
     *
     * @param Request  $request  PSR-7 request
     * @param Response $response PSR-7 response
     *
     * @return Response HTTP response
     */
    public function json(Request $request, Response $response)
    {
        $params = $request->getQueryParams();
        $format = $this->getFormat($request);
        if (isset($params['url'])) {
            try {
                return $response->withJson(
                    $this->download->getJSON(
                        $params['url'],
                        $format
                    )
                );
            } catch (Exception $e) {
                return $response->withJson(['error' => $e->getMessage()])
                    ->withStatus(500);
            }
        } else {
            return $response->withJson(['error' => 'You need to provide the url parameter'])
                ->withStatus(400);
        }
    }

    /**
     * Generate the canonical URL of the current page.
     *
     * @param Request $request PSR-7 Request
     *
     * @return string URL
     */
    private function getCanonicalUrl(Request $request)
    {
        $uri = $request->getUri();
        $return = 'https://alltubedownload.net/';

        $path = $uri->getPath();
        if ($path != '/') {
            $return .= $path;
        }

        $query = $uri->getQuery();
        if (!empty($query)) {
            $return .= '?'.$query;
        }

        return $return;
    }
}
