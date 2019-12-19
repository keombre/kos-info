<?php

namespace Kos;

use Exception;
use Goutte\Client;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;

class Kos
{

    const KOS_URI = "https://www.kos.cvut.cz";

    /** @var Client */
    private $client;

    /** @var SymfonyStyle */
    private $io;

    /** @var string */
    private $pageCode;

    private $semester;

    /**
     * Kos constructor.
     * @param string $username
     * @param SymfonyStyle $io
     * @throws Exception
     */
    function __construct(string $username, SymfonyStyle $io)
    {
        $this->io = $io;
        $this->client = new Client();

        $this->io->section('Signing into CVUT:KOS');
        $this->io->note('Remote server set to ' . self::KOS_URI);

        $this->login($username);

        $this->semester = $this->getCurrentSemester();

        $this->displayInfo();

        $this->mainLoop();
    }

    private function login(string $username)
    {
        while (true) {
            $crawler = $this->client->request('GET', $this->uri("/kos/logout.do"));
            $form = $crawler->filterXPath('//form[@name="login"]')->form();
            $password = $this->io->askHidden('Password');
            $form->setValues([
                'userName' => $username,
                'password' => $password
            ]);

            $crawler = $this->client->submit($form);
            if ($crawler->filterXPath('//*[@id="sessionLimit"]')->count() != 1) {
                $this->io->warning('Wrong username or password');
            } else {
                $this->io->success('Sign-in successful');
                return;
            }
        }
    }

    /**
     * @param string $uri
     * @param bool $internal
     * @return string
     * @throws Exception
     */
    private function uri(string $uri, bool $internal = false)
    {
        if ($internal) {
            if (is_null($this->pageCode)) {
                $this->pageCode = $this->getPageCode();
            }
            return self::KOS_URI . $uri . (strpos($uri, '?') === false ? '?' : '&') . 'page=' . $this->pageCode;
        }
        return self::KOS_URI . $uri;
    }

    /**
     * @param string $url
     * @return Crawler
     * @throws Exception
     */
    private function getCrawler(string $url = null)
    {
        if (!$this->isLogged()) {
            throw new Exception('User not signed in');
        } else {
            if (is_null($url)) {
                return $this->client->getCrawler();
            } else {
                return $this->client->request('GET', $this->uri($url, true));
            }
        }
    }

    /**
     * @return mixed
     * @throws Exception
     */
    private function getPageCode()
    {
        $crawler = $this->client->getCrawler();
        preg_match('/var pageCode=\'(.*?)\';/', $crawler->html(), $matches);
        if (count($matches) != 2) {
            throw new Exception('Page code not found');
        }
        return $matches[1];
    }

    /**
     * @return bool
     * @throws Exception
     */
    private function isLogged()
    {
        $crawler = $this->client->request('GET', $this->uri('/kos/toWelcome.do', true));
        return (bool)$crawler->filterXPath('//*[@id="hlavicka"]/div[2]/div[3]/b')->count();
    }

    /**
     * @throws Exception
     */
    private function displayInfo()
    {
        $this->io->text([
            'Your password expires in ' . $this->getPasswordExpires() . ' days',
            'Current semester: ' . $this->semester
        ]);
    }

    /**
     * @throws Exception
     */
    private function getPasswordExpires()
    {
        $crawler = $this->getCrawler();
        return (int)filter_var($crawler->filterXPath('//*[@id="hlavicka"]/div[2]/div[3]/b')->text(),
            FILTER_SANITIZE_NUMBER_INT);
    }

    /**
     * @throws Exception
     */
    private function getCurrentSemester()
    {
        $ret = new Semester();

        $crawler = $this->getCrawler('/kos/toWelcomeIFrame.do');
        $node = $crawler->filterXPath('//table/tr[4]/td/table/tr[1]/td[3]')->text();
        if (substr($node, 0, 1) == 'Z') {
            $ret->setPart(SemesterPart::WINTER);
        } else {
            $ret->setPart(SemesterPart::SUMMER);
        }

        preg_match('/20(\d{2})\//', $node, $matches);

        $node = $crawler->filterXPath('//table/tr[1]/td/table/tr[4]/td[2]')->text();
        if (substr($node, 0, 3) == '(B)') {
            $ret->setPlan(StudyPlan::BACHELOR);
        } else {
            $ret->setPlan(StudyPlan::MAGISTER);
        }

        return $ret->setYear((int)$matches[1]);
    }

    /**
     * @throws Exception
     */
    private function displayResults()
    {
        $this->io->table(['Subject', 'Closed', 'Credit', 'Grade'], $this->getResults());
    }

    /**
     * @throws Exception
     */
    private function getResults()
    {
        $ret = [];
        $crawler = $this->getCrawler('/kos/results.do');
        $table = $crawler->filterXPath('//*[@id="main-panel"]/table/tr/td[2]/table/tr[7]/td/table');
        $table->filterXPath('//tr')->each(function (Crawler $row) use (&$ret)
        {
            $children = $row->filterXPath('//td');
            if ($children->eq(0)->text() == (string)$this->semester) {
                $ret[] = [
                    $children->eq(2)->text(),
                    str_replace(['N', 'A'], ['No', 'Yes'], $children->eq(8)->text()),
                    str_replace(['Z', 'N'], ['Yes', 'No'], $children->eq(9)->text()),
                    $children->eq(10)->text()
                ];
            }
        });
        return $ret;
    }

    /**
     * @throws Exception
     */
    private function displayExams()
    {
        $exams = $this->getExams();
        $display = [];

        foreach ($exams as $subject => $exam) {
            $display[] = $subject;
            foreach ($exam as $e) {
                $display[] = implode(" ", $e);
            }
            $display[] = new TableSeparator();
        }

        $this->io->definitionList(...$display);
    }

    /**
     * @throws Exception
     */
    private function getExams()
    {
        $ret = [];
        $crawler = $this->getCrawler('/kos/examsTerms.do');
        $subjects = [];
        $crawler->filterXPath('//select/option')->each(function (Crawler $node) use (&$subjects, &$ret)
        {
            preg_match('/ - (.*?) /', $node->text(), $matches);
            $subjects[] = [
                $node->attr('value'),
                $matches[1]
            ];
            $ret[$matches[1]] = [];
        });

        $form = $crawler->filterXPath('//*[@id="main-panel"]/table/tr/td[2]/table/form')->reduce(
            function (Crawler $form)
            {
                $node = $form->getNode(0);
                $node->setAttribute('action', $this->uri('/kos/examsTerms.do', true));
                $node->setAttribute('method', 'post');
                return true;
            }
        )->form();

        foreach ($subjects as $subject) {
            $form->setValues([
                'action' => 'selectTerms',
                'selSubject' => $subject[0],
                'termId' => '',
                'examId' => '',
                'volnaKapacita' => '',
                'nahradnictviPovoleno' => ''
            ]);
            $child = $this->client->submit($form);
            $child->filterXPath('//tr[@class="tableRow1"]|//tr[@class="tableRow2"]')->each(
                function (Crawler $node) use (&$ret, $subject)
                {
                    $childCrawler = $node->filterXPath('//td');
                    preg_match('/\'(.*?)\'/', $childCrawler->last()->filterXPath('//a')->attr('href'), $id);
                    if ($childCrawler->count() == 20) {
                        $ret[$subject[1]][] = [
                            'Exam',
                            $childCrawler->eq(1)->text(),
                            $childCrawler->eq(6)->text(),
                            $childCrawler->eq(13)->text(),
                            $childCrawler->eq(15)->text(),
                            $id[1]
                        ];
                    } else {
                        $ret[$subject[1]][] = [
                            'Credit',
                            $childCrawler->eq(1)->text(),
                            $childCrawler->eq(6)->text(),
                            $id[1]
                        ];
                    }
                }
            );
        }

        return $ret;

    }

    /**
     * @throws Exception
     */
    private function mainLoop()
    {
        while (true) {
            switch ($this->io->choice('What do you want to do?', [
                'Display results',
                'Display exams',
                'Quit'
            ])) {
                case 'Display exams':
                    $this->displayExams();
                    break;
                case 'Display results':
                    $this->displayResults();
                    break;
                case 'Quit':
                    $this->io->text('Goodbye.');
                    return;
            }
        }
    }
}
