<?php
namespace CVS;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class HistoryCommand extends Command {

  public function configure() {

    $this->setName('history');
    $this->setDescription('Exibe historico');
    $this->setHelp('Exibe historico');

    $this->addArgument('arquivo', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'Arquivo para exibir historico');

    $this->addOption('tag', 't', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Tag');
  }

  public function execute($oInput, $oOutput) {

    try {

      $aParametroArquivos = $oInput->getArgument('arquivo');

      if ( !empty($aParametroArquivos) ) {

        foreach ( $aParametroArquivos as $iChave => $sParametroArquivo) {
          $aParametroArquivos[$iChave] = $this->getApplication()->clearPath($sParametroArquivo);
        }
      }

      $aParametroTag = $oInput->getOption('tag');
      $aTags = array();

      if ( !empty($aParametroTag) ) {

        foreach ( $aParametroTag as $sParametroTag) {
          $aTags[] = '-rT' . ltrim(strtoupper($sParametroTag), 'T');
        }
      }

      $aHistoricos = array();

      /**
       * Tag e arquivo 
       */
      if ( !empty($aTags) && !empty($aParametroArquivos) ) {

        foreach ( $aTags as $sTag ) {

          foreach ( $aParametroArquivos as $sParametroArquivo) {
            $aHistoricos[] = $this->getLog($sTag, $sParametroArquivo);
          }
        }
      } 

      /**
       * Tag 
       */
      if ( !empty($aTags) && empty($aParametroArquivos) ) {

        foreach ( $aTags as $sTag ) {
          $aHistoricos[] = $this->getLog($sTag);
        }
      }

      /**
       * Arquivo 
       */
      if ( empty($aTags) && !empty($aParametroArquivos) ) {

        foreach ( $aParametroArquivos as $sParametroArquivo) {
          $aHistoricos[] = $this->getLogPorArquivo($sParametroArquivo);
        }
      } 

      /**
       * Nenhum 
       */
      if ( empty($aTags) && empty($aParametroArquivos) ) {

        $aArquivos = $this->getArquivos();

        foreach ( $aArquivos as $sArquivo ) {
          $aHistoricos[] = $this->getLogPorArquivo($sArquivo);
        }
      }

      $oTabela = new \Table();
      $oTabela->setHeaders(array('Arquivo', 'Autor', 'Data', 'Hora', 'Versão', 'Tag', 'Mensagem'));

      if ( empty($aHistoricos) ) {
        throw new \Exception("Histórico não encontrado");
      }

      foreach($aHistoricos as $aHistorico ) {

        if ( empty($aHistorico) ) {
          throw new \Exception("Histórico não encontrado");
        }

        foreach ($aHistorico as $sArquivo => $aHistoricoArquivo) {

          foreach ($aHistoricoArquivo as $oHistorico ) {

            $oTabela->addRow(array(
              $oHistorico->sArquivo,
              $oHistorico->sAutor,
              $oHistorico->sData,
              $oHistorico->sHora,
              $oHistorico->iVersao,
              $oHistorico->sTags,
              \Encode::toUTF8($oHistorico->sMensagem)
            ));

          }

        }
      }

      $sOutput = $oTabela->render();
      $iColunas  = array_sum($oTabela->getWidths()); 
      $iColunas += count($oTabela->getWidths()) * 2;
      $iColunas += count($oTabela->getWidths()) - 1 ;

      if ( $iColunas > \Shell::columns() ) {

        $this->getApplication()->less($sOutput);
        return;
      }

      $oOutput->writeln($sOutput);

    } catch(\Exception $oErro) {
      $oOutput->writeln('<error>' . $oErro->getMessage() . '</error>');
    }
  }

  private function getTagsPorVersao($sArquivo, $iParametroVersao) {

    /**
     * Lista somenta as tags
     */
    exec('cvs log -h ' . $sArquivo . ' 2> /tmp/cvsgit_last_error', $aRetornoComandoTags, $iStatusComandoTags);

    if ( $iStatusComandoTags > 0 ) {

      throw new \Exception(
        'Erro ao execurar cvs log -h ' . $sArquivo . PHP_EOL . $this->getApplication()->getLastError()
      );
    }

    $aTagsPorVersao = array();
    $iVersaoAtual = 0;
    $lInicioListaTags = false;

    foreach( $aRetornoComandoTags as $iIndiceTags => $sLinhaRetornoTag ) {

      if ( strpos($sLinhaRetornoTag, 'head:') !== false ) {

        $iVersaoAtual = trim(str_replace('head:', '', $sLinhaRetornoTag));
        continue;
      }

      if ( strpos($sLinhaRetornoTag, 'symbolic names:') !== false ) {

        $lInicioListaTags = true;
        continue;
      }

      if ( $lInicioListaTags ) {

        if ( strpos($sLinhaRetornoTag, 'keyword substitution') !== false ) {
          break;
        }

        if ( strpos($sLinhaRetornoTag, 'total revisions') !== false ) {
          break;
        }

        $aLinhaTag = explode(':', $sLinhaRetornoTag);
        $iVersao   = trim($aLinhaTag[1]);
        $sTag      = trim($aLinhaTag[0]);

        $aTagsPorVersao[$iVersao][] = $sTag;
      }
    }

    if ( empty($aTagsPorVersao[$iParametroVersao]) ) {
      return array();
    }

    return $aTagsPorVersao[$iParametroVersao];
  }

  private function getLog($sParametroTag = null, $sParametroArquivo = null) {

    /**
     * Lista informacoes do commit, sem as tags
     */
    exec('cvs log -S -N ' . $sParametroTag . ' ' . $sParametroArquivo . ' 2> /tmp/cvsgit_last_error', $aRetornoComandoInformacoes, $iStatusComandoInformacoes);

    if ( $iStatusComandoInformacoes > 1 ) {

      Throw new \Exception(
        'Erro nº ' . $iStatusComandoInformacoes . ' - nao execurar cvs log -N ' . $sParametroArquivo . PHP_EOL .
        $this->getApplication()->getLastError()
      );
    }

    $iLinhaInformacaoCommit = 0;
    $aLinhasInformacaoCommit = array();

    foreach ( $aRetornoComandoInformacoes as $iIndice => $sLinhaRetorno ) {

      if ( empty($sLinhaRetorno) ) {
        continue;
      }

      /**
       * Versao
       */
      if ( strpos($sLinhaRetorno, 'RCS file:') !== false ) {

        $iLinhaInformacaoCommit = 0;
        continue;
      }

      $iLinhaInformacaoCommit++;

      $aLinhasInformacaoCommit[$iLinhaInformacaoCommit][] = $sLinhaRetorno;
    }

    if ( empty($aLinhasInformacaoCommit) ) {
      return array();
    }

    $iTotalLinhas = count($aLinhasInformacaoCommit[1]);

    for( $iIndice = 0; $iIndice < $iTotalLinhas; $iIndice++ ) {

      $sArquivo  = '';
      $sAutor    = '';
      $sData     = '';
      $sHora     = '';
      $iVersao   = '';
      $sMensagem = '';
      $sTagsVersao = '';

      $iVersao = trim(str_replace('revision', '', $aLinhasInformacaoCommit[10][$iIndice]));

      $sLinhaDataAutor = strtr($aLinhasInformacaoCommit[11][$iIndice], array('date:' => '', 'author:' => ''));
      $aLinhaDataAutor = explode(';', $sLinhaDataAutor);
      $sDataAutor = array_shift($aLinhaDataAutor);
      $aDataAutor = explode(' ', $sDataAutor);

      $sData  = implode('/', array_reverse(explode('-', $aDataAutor[1])));
      $sHora  = $aDataAutor[2];
      $sAutor = trim(array_shift($aLinhaDataAutor));

      $sMensagem = $aLinhasInformacaoCommit[12][$iIndice];

      $sTagsPorVersao = null;

      if ( !empty($aTagsPorVersao[$iVersao]) ) {
        $sTagsPorVersao = implode(', ', $aTagsPorVersao[$iVersao]);
      }

      $sArquivo = trim(str_replace('Working file:', '', $aLinhasInformacaoCommit[1][$iIndice]));

      $aTagsVersao = $this->getTagsPorVersao($sArquivo, $iVersao);
         
      if ( !empty($aTagsVersao) ) {
        $sTagsVersao = implode(', ', $aTagsVersao);
      }

      $oDadosHistorico = new \Stdclass();
      $oDadosHistorico->sArquivo  = $sArquivo;
      $oDadosHistorico->sAutor    = $sAutor;
      $oDadosHistorico->sData     = $sData;
      $oDadosHistorico->sHora     = $sHora;
      $oDadosHistorico->iVersao   = $iVersao;
      $oDadosHistorico->sMensagem = $sMensagem;
      $oDadosHistorico->sTags     = $sTagsVersao;

      $aHistorico[ $sArquivo ][] = $oDadosHistorico; 
    }

    return $aHistorico;
  }

  private function getArquivos() {

    /**
     * Lista informacoes do commit, sem as tags
     */
    exec('cvs log -S -N 2> /tmp/cvsgit_last_error', $aRetornoComandoInformacoes, $iStatusComandoInformacoes);

    if ( $iStatusComandoInformacoes > 1 ) {

      throw new \Exception(
        'Erro nº ' . $iStatusComandoInformacoes . ' - nao execurar cvs log -N ' . $sParametroArquivo . PHP_EOL .
        $this->getApplication()->getLastError() 
      );
    }

    $aArquivos = array();

    foreach ( $aRetornoComandoInformacoes as $iIndice => $sLinhaRetorno ) {

      if ( empty($sLinhaRetorno) ) {
        continue;
      }

      if ( strpos($sLinhaRetorno, 'Working file:') !== false ) {
        $aArquivos[] = trim(str_replace('Working file:', '', $sLinhaRetorno));
      }

    }

    return $aArquivos;
  }

  public function getLogPorArquivo($sArquivo) {

    if ( !file_exists($sArquivo) ) {
      throw new \Exception("Arquivo inválido: $sArquivo");
    } 

    $sArquivo = $this->getApplication()->clearPath($sArquivo);

    /**
     * Lista somenta as tags
     */
    exec('cvs log -h ' . $sArquivo . ' 2> /tmp/cvsgit_last_error', $aRetornoComandoTags, $iStatusComandoTags);

    if ( $iStatusComandoTags > 0 ) {

      throw new \Exception(
        'Erro ao execurar cvs log -h ' . $sArquivo . PHP_EOL . $this->getApplication()->getLastError() 
      );
    }
    
    $aTagsPorVersao = array();
    $iVersaoAtual = 0;
    $lInicioListaTags = false;

    foreach( $aRetornoComandoTags as $iIndiceTags => $sLinhaRetornoTag ) {

      if ( strpos($sLinhaRetornoTag, 'head:') !== false ) {

        $iVersaoAtual = trim(str_replace('head:', '', $sLinhaRetornoTag));
        continue;
      }

      if ( strpos($sLinhaRetornoTag, 'symbolic names:') !== false ) {

        $lInicioListaTags = true;
        continue;
      }

      if ( $lInicioListaTags ) {

        if ( strpos($sLinhaRetornoTag, 'keyword substitution') !== false ) {
          break;
        }

        if ( strpos($sLinhaRetornoTag, 'total revisions') !== false ) {
          break;
        }

        $aLinhaTag = explode(':', $sLinhaRetornoTag);
        $iVersao   = trim($aLinhaTag[1]);
        $sTag      = trim($aLinhaTag[0]);

        $aTagsPorVersao[$iVersao][] = $sTag;
      }
    }

    /**
     * Lista informacoes do commit, sem as tags
     */
    exec('cvs log -N ' . $sArquivo . ' 2> /tmp/cvsgit_last_error', $aRetornoComandoInformacoes, $iStatusComandoInformacoes);

    if ( $iStatusComandoInformacoes > 0 ) {

      throw new \Exception(
        'Erro ao execurar cvs log -N ' . $sArquivo . PHP_EOL .  $this->getApplication()->getLastError() 
      );
    }

    $iLinhaInformacaoCommit = 0;

    $aDadosLog = array();
    $iVersao   = '';
    $sAutor    = '';
    $sData     = '';
    $sData     = '';
    $sMensagem = '';
    $sTagsVersao = '';


    foreach ( $aRetornoComandoInformacoes as $iIndice => $sLinhaRetorno ) {

      if ( strpos($sLinhaRetorno, '------') !== false ) {
        continue;
      } 

      if ( $iLinhaInformacaoCommit == 1 && $iIndice > 11 ) {

        if ( !empty($aTagsPorVersao[$iVersao]) ) {
          $sTagsVersao = implode(', ', $aTagsPorVersao[$iVersao]);
        }

        $oDadosLog = new \Stdclass();
        $oDadosLog->sArquivo = $sArquivo;
        $oDadosLog->iVersao   = $iVersao;
        $oDadosLog->sAutor    = $sAutor;
        $oDadosLog->sData     = $sData;
        $oDadosLog->sHora     = $sHora;
        $oDadosLog->sMensagem = $sMensagem;
        $oDadosLog->sTags     = $sTagsVersao;

        $aDadosLog[ $sArquivo ][] = $oDadosLog;

        $iVersao   = '';
        $sAutor    = '';
        $sData     = '';
        $sData     = '';
        $sMensagem = '';
        $sTagsVersao = '';
      }

      if ( $iLinhaInformacaoCommit > 0 ) {
        $iLinhaInformacaoCommit--;
      } 

      /**
       * Versao
       */
      if ( strpos($sLinhaRetorno, 'revision') !== false && strpos($sLinhaRetorno, 'revision') === 0 ) {
        $iLinhaInformacaoCommit = 3;
      } 

      /**
       * Versao
       */
      if ( $iLinhaInformacaoCommit == 3 ) {

        $iVersao = trim(str_replace('revision', '', $sLinhaRetorno));
        continue;
      }

      /**
       * Data e autor 
       */
      if ( $iLinhaInformacaoCommit == 2 ) {

        $sLinhaRetorno = strtr($sLinhaRetorno, array('date:' => '', 'author:' => ''));
        $aLinhaInformacoesCommit = explode(';', $sLinhaRetorno);
        $sLinhaData = array_shift($aLinhaInformacoesCommit);
        $aLinhaData = explode(' ', $sLinhaData);

        $sData  = implode('/', array_reverse(explode('-', $aLinhaData[1])));
        $sHora  = $aLinhaData[2];

        $sAutor = trim(array_shift($aLinhaInformacoesCommit));
        continue;
      } 

      /**
       * Mensagem 
       */
      if ( $iLinhaInformacaoCommit == 1 ) {
        $sMensagem = $sLinhaRetorno;
      }
    }

    return $aDadosLog;
  }

}
